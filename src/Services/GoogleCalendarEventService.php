<?php

namespace GKCode\GoogleCalendar\Services;

use GKCode\GoogleCalendar\Models\GoogleCalendarConnection;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * CRUD directo sobre los eventos de Google Calendar (modulo AgendaGoogle).
 * No persiste nada localmente: Google es la fuente de datos.
 *
 * Eventos recurrentes: el listado se expande en ocurrencias (singleEvents) y las operaciones
 * reciben un alcance: instance (solo esta), following (esta y las siguientes) o all (toda la serie).
 */
class GoogleCalendarEventService
{
    public function __construct(
        private readonly GkServiciosCalendarClientService $client,
        private readonly GoogleCalendarSyncService $sync,
        private readonly RecurrenceRuleService $rules
    ) {
    }

    public function connectionFor(?int $companyId, int $userId): ?GoogleCalendarConnection
    {
        return $this->sync->connectionFor($companyId, $userId);
    }

    public function timezone(GoogleCalendarConnection $connection): string
    {
        return $connection->calendar_timezone ?: config('google-calendar.gkservicios.timezone');
    }

    /**
     * Devuelve ['ok' => bool, 'events' => array normalizado, 'error' => ?string].
     * $from y $to son fechas (Y-m-d) inclusivas.
     */
    public function list(GoogleCalendarConnection $connection, string $from, string $to, ?string $title = null): array
    {
        $tz = $this->timezone($connection);

        $result = $this->client->listEvents(
            (int) $connection->external_integration_id,
            $connection->calendar_id ?: 'primary',
            Carbon::parse($from, $tz)->startOfDay()->toIso8601String(),
            Carbon::parse($to, $tz)->endOfDay()->toIso8601String()
        );

        if (!$result['ok']) {
            return ['ok' => false, 'events' => [], 'error' => $result['error']];
        }

        $payload = (array) $result['payload'];
        $items = $payload['items'] ?? $payload['events'] ?? (array_is_list($payload) ? $payload : []);

        $events = collect($items)
            ->map(fn ($item) => $this->present((array) $item, $tz))
            ->filter(fn ($event) => $event['id'] && $event['status'] !== 'cancelled')
            ->when($title, fn ($c) => $c->filter(fn ($e) => Str::contains(Str::lower($e['title']), Str::lower($title))))
            ->sortBy('starts_at')
            ->values()
            ->all();

        return ['ok' => true, 'events' => $events, 'error' => null];
    }

    /**
     * Devuelve la regla de recurrencia (estructura Nexora) de la serie, o null si no es recurrente.
     */
    public function recurrence(GoogleCalendarConnection $connection, string $eventId): array
    {
        $master = $this->fetch($connection, $eventId);
        if (!$master['ok']) {
            return $master;
        }

        $rule = $this->rules->parse((array) ($master['payload']['recurrence'] ?? []), $this->timezone($connection));

        return ['ok' => true, 'payload' => ['recurrence' => $rule], 'error' => null];
    }

    public function create(GoogleCalendarConnection $connection, array $data): array
    {
        $payload = $this->buildPayload($connection, $data);
        if (!$payload) {
            return $this->invalidRange();
        }

        $recurrence = $this->recurrenceFromForm($connection, $data);
        if ($recurrence) {
            $payload['recurrence'] = $recurrence;
        }

        return $this->client->createEvent($payload, $this->client->idempotencyKey('agenda-create', Str::uuid()->toString()));
    }

    public function update(GoogleCalendarConnection $connection, string $eventId, array $data): array
    {
        $scope = $data['scope'] ?? null;
        $masterId = $data['recurring_event_id'] ?? null;

        if (!$scope || !$masterId) {
            return $this->save($connection, $eventId, $data, $this->recurrenceFromForm($connection, $data));
        }

        return match ($scope) {
            'instance' => $this->save($connection, $eventId, $data, null),
            'all' => $this->updateSeries($connection, $masterId, $data),
            default => $this->updateFollowing($connection, $masterId, $data),
        };
    }

    public function delete(GoogleCalendarConnection $connection, string $eventId, ?string $scope = null, ?string $masterId = null, ?string $originalStart = null): array
    {
        if (!$scope || !$masterId || $scope === 'instance') {
            return $this->deleteOne($connection, $eventId);
        }

        if ($scope === 'all') {
            return $this->deleteOne($connection, $masterId);
        }

        $master = $this->fetch($connection, $masterId);
        if (!$master['ok']) {
            return $master;
        }

        $raw = $master['payload'];
        $tz = $this->timezone($connection);
        if (!$originalStart || $this->isFirstOccurrence($raw, $originalStart, $tz)) {
            return $this->deleteOne($connection, $masterId);
        }

        return $this->truncateMaster($connection, $masterId, $raw, Carbon::parse($originalStart, $tz));
    }

    private function save(GoogleCalendarConnection $connection, string $eventId, array $data, ?array $recurrence): array
    {
        $payload = $this->buildPayload($connection, $data);
        if (!$payload) {
            return $this->invalidRange();
        }

        if ($recurrence) {
            $payload['recurrence'] = $recurrence;
        }

        return $this->pushUpdate($eventId, $payload);
    }

    private function updateSeries(GoogleCalendarConnection $connection, string $masterId, array $data): array
    {
        $master = $this->fetch($connection, $masterId);
        if (!$master['ok']) {
            return $master;
        }

        $raw = $master['payload'];
        $tz = $this->timezone($connection);

        $newStart = Carbon::parse($data['starts_at'], $tz);
        $previous = Carbon::parse($data['previous_starts_at'] ?? $data['starts_at'], $tz);
        $start = $this->rawStart($raw, $tz)->addSeconds($newStart->getTimestamp() - $previous->getTimestamp());
        $end = !empty($data['ends_at'])
            ? $start->copy()->addSeconds(Carbon::parse($data['ends_at'], $tz)->getTimestamp() - $newStart->getTimestamp())
            : null;

        $payload = $this->sync->payload($connection, $data['title'], $data['description'] ?? null, $start, $end, $data['location'] ?? null);
        if (!$payload) {
            return $this->invalidRange();
        }
        $payload = $this->withReminders($payload, $data);

        $existing = (array) ($raw['recurrence'] ?? []);
        $form = $data['recurrence'] ?? null;
        if (!empty($form['readonly'])) {
            $lines = $existing;
        } elseif (empty($form['freq'])) {
            $lines = [];
        } else {
            $lines = $this->rules->replaceRule($existing, $this->rules->build($form, $start, $tz));
        }

        if ($lines) {
            $payload['recurrence'] = $lines;
        }

        return $this->pushUpdate($masterId, $payload);
    }

    private function updateFollowing(GoogleCalendarConnection $connection, string $masterId, array $data): array
    {
        $master = $this->fetch($connection, $masterId);
        if (!$master['ok']) {
            return $master;
        }

        $raw = $master['payload'];
        $tz = $this->timezone($connection);
        $originalStart = $data['original_starts_at'] ?? null;

        if (!$originalStart || $this->isFirstOccurrence($raw, $originalStart, $tz)) {
            return $this->updateSeries($connection, $masterId, $data);
        }

        $existing = (array) ($raw['recurrence'] ?? []);
        $form = $data['recurrence'] ?? null;
        if (!empty($form['readonly'])) {
            $lines = $this->rules->withoutEnd($existing);
        } else {
            $lines = $this->recurrenceFromForm($connection, $data) ?? [];
        }

        $payload = $this->buildPayload($connection, $data);
        if (!$payload) {
            return $this->invalidRange();
        }
        if ($lines) {
            $payload['recurrence'] = $lines;
        }

        // Se crea primero la nueva serie: si falla, la serie original queda intacta.
        $created = $this->client->createEvent($payload, $this->client->idempotencyKey('agenda-create', Str::uuid()->toString()));
        if (!$created['ok']) {
            return $created;
        }

        $truncated = $this->truncateMaster($connection, $masterId, $raw, Carbon::parse($originalStart, $tz));
        if (!$truncated['ok']) {
            return $truncated;
        }

        return $created;
    }

    private function truncateMaster(GoogleCalendarConnection $connection, string $masterId, array $raw, Carbon $before): array
    {
        $tz = $this->timezone($connection);
        $payload = $this->sync->payload(
            $connection,
            $raw['summary'] ?? '(Sin titulo)',
            $raw['description'] ?? null,
            $this->rawStart($raw, $tz),
            $this->rawEnd($raw, $tz),
            $raw['location'] ?? null
        );
        if (!$payload) {
            return $this->invalidRange();
        }

        if (isset($raw['reminders'])) {
            $payload['reminders'] = $raw['reminders'];
        }
        $payload['recurrence'] = $this->rules->truncate((array) ($raw['recurrence'] ?? []), $before);

        return $this->pushUpdate($masterId, $payload);
    }

    private function deleteOne(GoogleCalendarConnection $connection, string $eventId): array
    {
        return $this->client->deleteEvent(
            (int) $connection->external_integration_id,
            $connection->calendar_id ?: 'primary',
            $eventId,
            $this->client->idempotencyKey('agenda-delete', $connection->id . '|' . $eventId)
        );
    }

    private function pushUpdate(string $eventId, array $payload): array
    {
        return $this->client->updateEvent(
            $eventId,
            $payload,
            $this->client->idempotencyKey('agenda-update', $eventId . '|' . json_encode($payload))
        );
    }

    private function fetch(GoogleCalendarConnection $connection, string $eventId): array
    {
        return $this->client->getEvent(
            (int) $connection->external_integration_id,
            $connection->calendar_id ?: 'primary',
            $eventId
        );
    }

    private function recurrenceFromForm(GoogleCalendarConnection $connection, array $data): ?array
    {
        $form = $data['recurrence'] ?? null;
        if (empty($form['freq'])) {
            return null;
        }

        $tz = $this->timezone($connection);

        return [$this->rules->build($form, Carbon::parse($data['starts_at'], $tz), $tz)];
    }

    private function isFirstOccurrence(array $raw, string $originalStart, string $tz): bool
    {
        return $this->rawStart($raw, $tz)->format('Y-m-d\TH:i') === Carbon::parse($originalStart, $tz)->format('Y-m-d\TH:i');
    }

    private function rawStart(array $raw, string $tz): Carbon
    {
        return Carbon::parse($raw['start']['dateTime'] ?? $raw['start']['date'], $tz)->setTimezone($tz);
    }

    private function rawEnd(array $raw, string $tz): ?Carbon
    {
        $end = $raw['end']['dateTime'] ?? $raw['end']['date'] ?? null;

        return $end ? Carbon::parse($end, $tz)->setTimezone($tz) : null;
    }

    private function buildPayload(GoogleCalendarConnection $connection, array $data): ?array
    {
        $tz = $this->timezone($connection);
        $start = Carbon::parse($data['starts_at'], $tz);
        $end = !empty($data['ends_at']) ? Carbon::parse($data['ends_at'], $tz) : null;

        $payload = $this->sync->payload(
            $connection,
            $data['title'],
            $data['description'] ?? null,
            $start,
            $end,
            $data['location'] ?? null
        );

        return $payload ? $this->withReminders($payload, $data) : null;
    }

    // reminders null = recordatorios predeterminados del calendario; array = lista personalizada (puede ser vacia).
    private function withReminders(array $payload, array $data): array
    {
        $reminders = $data['reminders'] ?? null;

        $payload['reminders'] = is_array($reminders)
            ? ['useDefault' => false, 'overrides' => array_map(
                fn ($r) => ['method' => $r['method'], 'minutes' => (int) $r['minutes']],
                array_values($reminders)
            )]
            : ['useDefault' => true];

        return $payload;
    }

    private function invalidRange(): array
    {
        return ['ok' => false, 'payload' => null, 'error' => 'La fecha de fin debe ser posterior a la de inicio.', 'code' => 'VALIDATION', 'status' => 422];
    }

    private function presentReminders(array $item): ?array
    {
        $reminders = $item['reminders'] ?? null;
        if (!$reminders || !empty($reminders['useDefault'])) {
            return null;
        }

        return collect($reminders['overrides'] ?? [])
            ->map(fn ($r) => ['method' => $r['method'] ?? 'popup', 'minutes' => (int) ($r['minutes'] ?? 0)])
            ->values()
            ->all();
    }

    // Normaliza un evento de Google al formato usado por la vista (fechas locales del calendario).
    private function present(array $item, string $tz): array
    {
        $startRaw = $item['start']['dateTime'] ?? $item['start']['date'] ?? null;
        $endRaw = $item['end']['dateTime'] ?? $item['end']['date'] ?? null;
        $allDay = isset($item['start']['date']) && !isset($item['start']['dateTime']);
        $format = $allDay ? 'Y-m-d' : 'Y-m-d\TH:i';
        $originalRaw = $item['originalStartTime']['dateTime'] ?? $item['originalStartTime']['date'] ?? null;

        return [
            'id' => $item['id'] ?? null,
            'title' => $item['summary'] ?? '(Sin titulo)',
            'description' => $item['description'] ?? '',
            'location' => $item['location'] ?? '',
            'starts_at' => $startRaw ? Carbon::parse($startRaw)->setTimezone($tz)->format($format) : null,
            'ends_at' => $endRaw ? Carbon::parse($endRaw)->setTimezone($tz)->format($format) : null,
            'all_day' => $allDay,
            'status' => $item['status'] ?? 'confirmed',
            'html_link' => $item['htmlLink'] ?? $item['html_link'] ?? null,
            'reminders' => $this->presentReminders($item),
            'is_recurring' => !empty($item['recurringEventId']) || !empty($item['recurrence']),
            'recurring_event_id' => $item['recurringEventId'] ?? null,
            'original_starts_at' => $originalRaw ? Carbon::parse($originalRaw)->setTimezone($tz)->format($format) : null,
        ];
    }
}
