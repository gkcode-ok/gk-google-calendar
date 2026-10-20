<?php

namespace GKCode\GoogleCalendar\Services;

use GKCode\GoogleCalendar\Models\GkServiciosTenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Unica puerta de salida hacia GK Servicios (Google Calendar).
 * Todas las respuestas se normalizan a: ok, payload, error, code, status.
 */
class GkServiciosCalendarClientService
{
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar.readonly',
        'https://www.googleapis.com/auth/calendar.events',
    ];

    public function __construct(private readonly GkServiciosTenantService $tenants)
    {
    }

    public function hasConfig(): bool
    {
        return $this->baseUrl() !== '' && $this->tenant() !== null;
    }

    private function tenant(): ?GkServiciosTenant
    {
        return $this->tenants->forCompany($this->tenants->resolveCompanyId());
    }

    public function defaultScopes(): array
    {
        return self::SCOPES;
    }

    public function createIntegration(string $name, array $scopes = [], array $settings = []): array
    {
        $ids = $this->tenants->catalogIds();

        return $this->send('post', '/integrations', [
            'integration_provider_id' => $ids['provider_id'],
            'integration_service_id' => $ids['service_id'],
            'name' => $name,
            'scopes' => $scopes ?: self::SCOPES,
            'settings' => $settings,
        ], [], $this->idempotencyKey('integration', $name . '|' . json_encode($settings)));
    }

    public function getIntegration(int $integrationId): array
    {
        return $this->send('get', "/integrations/{$integrationId}");
    }

    public function disconnectIntegration(int $integrationId): array
    {
        return $this->send('delete', "/integrations/{$integrationId}", [], [], $this->idempotencyKey('disconnect', (string) $integrationId));
    }

    public function beginOAuth(int $integrationId): array
    {
        return $this->send('post', "/integrations/{$integrationId}/oauth/authorize", [], [], $this->idempotencyKey('oauth', $integrationId . '|' . now()->timestamp));
    }

    public function oauthStatus(int $integrationId): array
    {
        return $this->send('get', "/integrations/{$integrationId}/oauth/status");
    }

    public function listCalendars(int $integrationId): array
    {
        return $this->send('get', '/calendar/calendars', [], ['integration_id' => $integrationId]);
    }

    public function getCalendar(int $integrationId, string $calendarId): array
    {
        return $this->send('get', '/calendar/calendars/' . rawurlencode($calendarId), [], ['integration_id' => $integrationId]);
    }

    public function listEvents(int $integrationId, string $calendarId, string $from, string $to): array
    {
        return $this->send('get', '/calendar/events', [], [
            'integration_id' => $integrationId,
            'calendar_id' => $calendarId,
            'from' => $from,
            'to' => $to,
            'singleEvents' => 1,
        ]);
    }

    public function getEvent(int $integrationId, string $calendarId, string $eventId): array
    {
        return $this->send('get', '/calendar/events/' . rawurlencode($eventId), [], [
            'integration_id' => $integrationId,
            'calendar_id' => $calendarId,
        ]);
    }

    public function createEvent(array $payload, string $idempotencyKey): array
    {
        return $this->send('post', '/calendar/events', $payload, [], $idempotencyKey);
    }

    public function updateEvent(string $eventId, array $payload, string $idempotencyKey): array
    {
        return $this->send('put', '/calendar/events/' . rawurlencode($eventId), $payload, [], $idempotencyKey);
    }

    public function deleteEvent(int $integrationId, string $calendarId, string $eventId, string $idempotencyKey): array
    {
        return $this->send('delete', '/calendar/events/' . rawurlencode($eventId), [], [
            'integration_id' => $integrationId,
            'calendar_id' => $calendarId,
        ], $idempotencyKey);
    }

    /**
     * Clave valida para GK Servicios (8-128 caracteres: letras, numeros . _ : -).
     */
    public function idempotencyKey(string $scope, string $seed): string
    {
        return 'nexora:' . $scope . ':' . substr(hash('sha256', $seed), 0, 40);
    }

    private function send(string $method, string $path, array $body = [], array $query = [], ?string $idempotencyKey = null): array
    {
        $tenant = $this->tenant();
        if (!$tenant || $this->baseUrl() === '') {
            return $this->failure('Google Calendar no esta habilitado para esta empresa.', 'NOT_CONFIGURED');
        }

        $isWrite = in_array($method, ['post', 'put', 'patch', 'delete'], true);
        if ($isWrite && !$idempotencyKey) {
            return $this->failure('Falta la clave de idempotencia para la operacion.', 'IDEMPOTENCY_REQUIRED');
        }

        $requestId = (string) Str::uuid();
        $url = $this->baseUrl() . $path;

        try {
            $request = $this->request($tenant, $requestId, $idempotencyKey);
            $response = match ($method) {
                'get' => $request->get($url, $query),
                'delete' => $request->delete($url . ($query ? '?' . http_build_query($query) : ''), $body),
                default => $request->{$method}($url . ($query ? '?' . http_build_query($query) : ''), $body),
            };
        } catch (ConnectionException $e) {
            Log::warning('GKServicios sin conexion', ['endpoint' => $path, 'request_id' => $requestId]);

            return $this->failure('No fue posible comunicarse con GK Servicios.', 'CONNECTION_ERROR');
        }

        return $this->normalize($response, $path, $requestId);
    }

    private function request(GkServiciosTenant $tenant, string $requestId, ?string $idempotencyKey): PendingRequest
    {
        $headers = [
            'X-Client-ID' => (string) $tenant->client_id,
            'X-Client-Secret' => (string) $tenant->client_secret,
            'X-Tenant-Token' => (string) $tenant->tenant_token,
            'X-Request-ID' => $requestId,
        ];

        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return Http::acceptJson()->asJson()->timeout(20)->withHeaders($headers);
    }

    private function normalize(Response $response, string $path, string $requestId): array
    {
        $json = (array) $response->json();
        $apiOk = $response->successful() && ($json['success'] ?? false) === true && (int) ($json['status'] ?? 0) === 0;

        if ($apiOk) {
            return [
                'ok' => true,
                'payload' => $json['data'] ?? [],
                'error' => null,
                'code' => null,
                'status' => $response->status(),
            ];
        }

        $error = $json['error'] ?? [];
        $message = $error['message'] ?? $json['descripcion'] ?? 'Error desconocido en GK Servicios.';
        $code = $error['code'] ?? null;
        foreach ((array) ($json['errors'] ?? []) as $messages) {
            $message .= ' ' . implode(' ', (array) $messages);
        }
        Log::warning('GKServicios error', [
            'service' => 'GKServicios',
            'endpoint' => $path,
            'http_status' => $response->status(),
            'api_status' => $json['status'] ?? null,
            'code' => $code,
            'request_id' => $error['request_id'] ?? $requestId,
        ]);

        return $this->failure($message, $code, $response->status());
    }

    private function failure(string $message, ?string $code = null, ?int $status = null): array
    {
        return ['ok' => false, 'payload' => null, 'error' => $message, 'code' => $code, 'status' => $status];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('google-calendar.gkservicios.base_url'), '/');
    }
}
