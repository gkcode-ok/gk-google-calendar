<?php

namespace GKCode\GoogleCalendar\Services;

use GKCode\GoogleCalendar\Enums\GoogleSyncStatus;
use GKCode\GoogleCalendar\Models\GoogleCalendarConnection;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Reglas de negocio de sincronizacion Agenda -> Google Calendar.
 * La agenda local es el sistema de registro: un error remoto nunca rompe el CRUD local.
 */
class GoogleCalendarSyncService
{
    private const DEFAULT_DURATION_MINUTES = 60;

    public function __construct(private readonly GkServiciosCalendarClientService $client)
    {
    }

    public function connectionFor(?int $companyId, int $userId): ?GoogleCalendarConnection
    {
        $companyId = $companyId ?: (int) config('google-calendar.default_company_id', 1);

        $connection = GoogleCalendarConnection::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->first();

        return $connection && $connection->isConnected() ? $connection : null;
    }

    /**
     * Crea o actualiza el evento remoto. Devuelve el resultado normalizado del cliente
     * o null si no corresponde sincronizar (sin conexion activa).
     */
    public function sync(Model $event): ?array
    {
        $connection = $this->connectionFor($event->company_id ?? null, (int) $event->user_id);
        if (!$connection) {
            return null;
        }

        if ($event->status === 'cancelled') {
            return $event->google_event_id ? $this->removeRemote($event, $connection) : null;
        }

        $payload = $this->buildPayload($event, $connection);
        if (!$payload) {
            return $this->markError($event, 'La fecha de fin debe ser posterior a la de inicio.');
        }

        $hash = hash('sha256', json_encode($payload));
        if ($event->google_event_id && $event->google_payload_hash === $hash && $event->google_sync_status === GoogleSyncStatus::SYNCED) {
            return ['ok' => true, 'payload' => [], 'error' => null, 'code' => null, 'status' => 200];
        }

        $result = $event->google_event_id
            ? $this->client->updateEvent($event->google_event_id, $payload, $this->client->idempotencyKey('update', $event->id . '|' . $hash))
            : $this->client->createEvent($payload, $this->client->idempotencyKey('create', $event->id . '|' . $hash));

        // El evento remoto ya no existe: se recrea con una clave nueva.
        if (!$result['ok'] && $event->google_event_id && ($result['status'] ?? null) === 404) {
            $result = $this->client->createEvent($payload, $this->client->idempotencyKey('recreate', $event->id . '|' . $hash));
        }

        if (!$result['ok']) {
            return $this->markError($event, $result['error'], $result);
        }

        $remote = (array) $result['payload'];
        $remote = (array) ($remote['event'] ?? $remote);

        $event->forceFill([
            'google_calendar_connection_id' => $connection->id,
            'google_calendar_id' => $connection->calendar_id,
            'google_event_id' => $remote['id'] ?? $event->google_event_id,
            'google_event_html_link' => $remote['htmlLink'] ?? $remote['html_link'] ?? $event->google_event_html_link,
            'google_sync_status' => GoogleSyncStatus::SYNCED,
            'google_synced_at' => now(),
            'google_last_error' => null,
            'google_payload_hash' => $hash,
        ])->saveQuietly();

        $connection->forceFill(['last_sync_at' => now()])->save();

        return $result;
    }

    /**
     * Elimina el evento remoto antes del borrado local. Devuelve null si no hay nada que borrar.
     */
    public function deleteRemote(Model $event): ?array
    {
        if (!$event->google_event_id) {
            return null;
        }

        $connection = $this->connectionFor($event->company_id ?? null, (int) $event->user_id);
        if (!$connection) {
            return null;
        }

        $result = $this->client->deleteEvent(
            (int) $connection->external_integration_id,
            $event->google_calendar_id ?: $connection->calendar_id,
            $event->google_event_id,
            $this->client->idempotencyKey('delete', $event->id . '|' . $event->google_event_id)
        );

        // Si ya no existe en Google, el objetivo se cumplio.
        if (!$result['ok'] && ($result['status'] ?? null) === 404) {
            return ['ok' => true, 'payload' => [], 'error' => null, 'code' => null, 'status' => 404];
        }

        if (!$result['ok']) {
            $this->logError($event, $result);
        }

        return $result;
    }

    private function removeRemote(Model $event, GoogleCalendarConnection $connection): array
    {
        $result = $this->deleteRemote($event) ?? ['ok' => true];

        if ($result['ok']) {
            $event->forceFill([
                'google_event_id' => null,
                'google_event_html_link' => null,
                'google_sync_status' => GoogleSyncStatus::DELETED_REMOTE,
                'google_synced_at' => now(),
                'google_last_error' => null,
                'google_payload_hash' => null,
            ])->saveQuietly();

            return $result;
        }

        return $this->markError($event, $result['error'], $result);
    }

    private function buildPayload(Model $event, GoogleCalendarConnection $connection): ?array
    {
        return $this->payload(
            $connection,
            $event->title,
            trim(($event->type ? "[{$event->type}] " : '') . ($event->description ?? '')),
            $event->starts_at,
            $event->ends_at
        );
    }

    /**
     * Arma el payload de evento para GK Servicios. Devuelve null si el rango de fechas es invalido.
     */
    public function payload(
        GoogleCalendarConnection $connection,
        string $summary,
        ?string $description,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        ?string $location = null
    ): ?array {
        $timezone = $connection->calendar_timezone ?: config('google-calendar.gkservicios.timezone');
        $start = $startsAt->copy()->setTimezone($timezone);
        $end = $endsAt
            ? $endsAt->copy()->setTimezone($timezone)
            : $start->copy()->addMinutes(self::DEFAULT_DURATION_MINUTES);

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        return array_filter([
            'integration_id' => (int) $connection->external_integration_id,
            'calendar_id' => $connection->calendar_id ?: 'primary',
            'summary' => $summary,
            'description' => $description,
            'location' => $location,
            'start' => ['dateTime' => $start->toIso8601String(), 'timeZone' => $timezone],
            'end' => ['dateTime' => $end->toIso8601String(), 'timeZone' => $timezone],
        ], fn ($value) => $value !== '' && $value !== null);
    }

    private function markError(Model $event, ?string $message, array $result = []): array
    {
        $event->forceFill([
            'google_sync_status' => GoogleSyncStatus::ERROR,
            'google_last_error' => $message,
        ])->saveQuietly();

        $this->logError($event, $result ?: ['error' => $message]);

        return $result ?: ['ok' => false, 'payload' => null, 'error' => $message, 'code' => 'VALIDATION', 'status' => 422];
    }

    private function logError(Model $event, array $result): void
    {
        Log::warning('GoogleCalendar sync fallo', [
            'company_id' => $event->company_id ?? null,
            'user_id' => $event->user_id,
            'agenda_event_id' => $event->id,
            'code' => $result['code'] ?? null,
            'http_status' => $result['status'] ?? null,
        ]);
    }
}
