<?php

namespace GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar;

use GKCode\GoogleCalendar\Enums\GoogleCalendarConnectionStatus as Status;
use App\Http\Controllers\BaseController;
use GKCode\GoogleCalendar\Models\GoogleCalendarConnection;
use App\Models\System\AgendaEvent;
use App\Models\System\Company;
use GKCode\GoogleCalendar\Services\GkServiciosTenantService;
use GKCode\GoogleCalendar\Services\GkServiciosCalendarClientService;
use GKCode\GoogleCalendar\Services\GoogleCalendarSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoogleCalendarConnectionController extends BaseController
{
    protected $modelo = 'Conexion Google Calendar';
    protected $mainModel = GoogleCalendarConnection::class;

    public function __construct(
        private readonly GkServiciosCalendarClientService $client,
        private readonly GoogleCalendarSyncService $sync,
        private readonly GkServiciosTenantService $tenants
    ) {
    }

    public function estado(): array
    {
        $connection = $this->currentConnection();

        return [
            'status' => 0,
            'descripcion' => 'Estado de Google Calendar',
            'data' => $connection ? $this->present($connection) : $this->emptyState(),
            'generalConfig' => GoogleCalendarConnection::generalConfig(),
        ];
    }

    public function conectar(): array
    {
        $user = Auth::user();

        if (!$user) {
            return ['status' => -1, 'descripcion' => 'Sesion no valida; vuelve a iniciar sesion.'];
        }

        if (!$user->company_id) {
            return ['status' => -1, 'descripcion' => 'Tu usuario no pertenece a una empresa; ingresa con un usuario de empresa para conectar Google Calendar.'];
        }

        // Alta automatica en GK Servicios la primera vez que la empresa se conecta.
        if (!$this->tenants->forCompany($user->company_id)) {
            $company = Company::find($user->company_id);
            $registered = $company ? $this->tenants->enable($company) : ['ok' => false, 'error' => 'Empresa no encontrada.'];
            if (!$registered['ok']) {
                return ['status' => -1, 'descripcion' => 'No fue posible registrar la empresa en GK Servicios: ' . $registered['error']];
            }
        }

        if (!$this->client->hasConfig()) {
            return ['status' => -1, 'descripcion' => 'Google Calendar no esta habilitado para tu empresa. Contacta al administrador del sistema.'];
        }

        $connection = $this->currentConnection() ?? new GoogleCalendarConnection([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
        ]);

        if (!$connection->external_integration_id) {
            $created = $this->client->createIntegration(
                'Nexora - ' . $user->name,
                [],
                ['calendar_timezone' => config('google-calendar.gkservicios.timezone')]
            );

            if (!$created['ok']) {
                return $this->fail($connection, 'No fue posible crear la integracion: ' . $created['error']);
            }

            $connection->external_integration_id = (int) ($created['payload']['id'] ?? $created['payload']['integration']['id'] ?? 0);
            if ($connection->external_integration_id <= 0) {
                return $this->fail($connection, 'GK Servicios no devolvio los datos de la integracion.');
            }

            $connection->name = 'Nexora - ' . $user->name;
            $connection->scopes_requested = $this->client->defaultScopes();
        }

        $authorize = $this->client->beginOAuth((int) $connection->external_integration_id);
        Log::info('GOOGLE_OAUTH_INITIATED', [
            'integration_id' => $connection->external_integration_id,
            'company_id' => $connection->company_id,
            'user_id' => Auth::id(),
        ]);
        if (!$authorize['ok'] || empty($authorize['payload']['authorization_url'])) {
            return $this->fail($connection, 'No fue posible iniciar la autorizacion con Google: ' . ($authorize['error'] ?? 'respuesta incompleta'));
        }

        $connection->status = Status::PENDING;
        $connection->last_error = null;
        $connection->save();

        return [
            'status' => 0,
            'descripcion' => 'Autoriza el acceso en la ventana de Google.',
            'data' => array_merge($this->present($connection), [
                'authorization_url' => $authorize['payload']['authorization_url'],
            ]),
            'authorization_url' => $authorize['payload']['authorization_url'],
        ];
    }

    public function oauthStatus(): array
    {
        $connection = $this->currentConnection();
        if (!$connection || !$connection->external_integration_id) {
            return ['status' => -1, 'descripcion' => 'Primero debes iniciar la conexion con Google Calendar.'];
        }

        $remote = $this->client->oauthStatus((int) $connection->external_integration_id);
        if (!$remote['ok']) {
            return $this->fail($connection, 'No fue posible verificar la autorizacion: ' . $remote['error']);
        }

        $payload = (array) $remote['payload'];
        $remoteStatus = $payload['integration_status'] ?? $payload['status'] ?? null;

        Log::info('GOOGLE_OAUTH_STATUS_CHECKED', [
            'integration_id' => $connection->external_integration_id,
            'company_id' => $connection->company_id,
            'user_id' => Auth::id(),
            'remote_status' => $remoteStatus,
            'connected' => $payload['connected'] ?? null,
        ]);

        if (in_array($remoteStatus, ['error', 'revoked', 'expired'], true) && ($payload['connected'] ?? false) !== true) {
            $detail = $payload['error'] ?? $payload['last_error'] ?? $remoteStatus;

            return $this->fail($connection, 'Error de autorizacion: ' . $detail);
        }

        if (($payload['connected'] ?? false) === true) {
            $connection->status = Status::CONNECTED;
            $connection->last_connection_at = now();
            $connection->last_error = null;
            $connection->token_expires_at = $payload['token_expires_at'] ?? $payload['expires_at'] ?? null;
            $connection->scopes_granted = $payload['scopes_granted'] ?? $payload['scopes'] ?? null;
            $connection->save();

            return ['status' => 0, 'descripcion' => 'Google Calendar conectado correctamente.', 'data' => $this->present($connection)];
        }

        return ['status' => 0, 'descripcion' => 'La autorizacion con Google todavia esta pendiente.', 'data' => $this->present($connection)];
    }

    public function calendarios(): array
    {
        $connection = $this->currentConnection();
        if (!$connection || !$connection->isConnected()) {
            return ['status' => -1, 'descripcion' => 'Google Calendar no esta conectado.'];
        }

        $remote = $this->client->listCalendars((int) $connection->external_integration_id);
        if (!$remote['ok']) {
            return ['status' => -1, 'descripcion' => 'No fue posible obtener los calendarios: ' . $remote['error']];
        }

        $items = collect($remote['payload']['items'] ?? [])->map(fn ($item) => [
            'id' => $item['id'] ?? null,
            'summary' => $item['summary'] ?? ($item['id'] ?? ''),
            'timeZone' => $item['timeZone'] ?? null,
            'primary' => (bool) ($item['primary'] ?? false),
        ])->values();

        return ['status' => 0, 'descripcion' => 'Calendarios disponibles', 'data' => $items];
    }

    public function calendarioPrincipal(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'calendar_id' => ['required', 'string', 'max:190'],
        ])->validate();

        $connection = $this->currentConnection();
        if (!$connection || !$connection->isConnected()) {
            return ['status' => -1, 'descripcion' => 'Google Calendar no esta conectado.'];
        }

        // Se valida contra GK Servicios: el usuario no puede fijar un calendario arbitrario.
        $remote = $this->client->getCalendar((int) $connection->external_integration_id, $data['calendar_id']);
        if (!$remote['ok']) {
            return ['status' => -1, 'descripcion' => 'El calendario seleccionado no es valido: ' . $remote['error']];
        }

        $calendar = (array) ($remote['payload']['calendar'] ?? $remote['payload']);
        $connection->calendar_id = $data['calendar_id'];
        $connection->calendar_summary = $calendar['summary'] ?? $data['calendar_id'];
        $connection->calendar_timezone = $calendar['timeZone'] ?? config('google-calendar.gkservicios.timezone');
        $connection->save();

        return ['status' => 0, 'descripcion' => 'Calendario guardado correctamente.', 'data' => $this->present($connection)];
    }

    public function sincronizarEvento($id): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['status' => -1, 'descripcion' => 'Sesion no valida; vuelve a iniciar sesion.'];
        }
        $event = AgendaEvent::where('company_id', $user->company_id)->where('user_id', $user->id)->find($id);
        if (!$event) {
            return ['status' => -1, 'descripcion' => 'Evento no encontrado'];
        }

        $result = $this->sync->sync($event);
        if ($result === null) {
            return ['status' => -1, 'descripcion' => 'Google Calendar no esta conectado.'];
        }
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => 'No fue posible sincronizar el evento: ' . $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Evento sincronizado correctamente.', 'data' => $event->fresh()];
    }

    public function desconectar(): array
    {
        $connection = $this->currentConnection();
        if (!$connection) {
            return ['status' => 0, 'descripcion' => 'Google Calendar ya estaba desconectado.', 'data' => $this->emptyState()];
        }

        if ($connection->external_integration_id) {
            $remote = $this->client->disconnectIntegration((int) $connection->external_integration_id);
            // Si la integracion ya no existe en GK Servicios, igual se limpia el estado local.
            if (!$remote['ok'] && ($remote['status'] ?? null) !== 404) {
                return $this->fail($connection, 'No fue posible desconectar: ' . $remote['error']);
            }
        }

        $connection->status = Status::DISCONNECTED;
        $connection->external_integration_id = null;
        $connection->token_expires_at = null;
        $connection->last_error = null;
        $connection->save();

        return ['status' => 0, 'descripcion' => 'Google Calendar fue desconectado correctamente.', 'data' => $this->present($connection)];
    }

    private function currentConnection(): ?GoogleCalendarConnection
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        return GoogleCalendarConnection::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function fail(GoogleCalendarConnection $connection, string $message): array
    {
        $connection->status = Status::ERROR;
        $connection->last_error = $message;
        $connection->save();

        return ['status' => -1, 'descripcion' => $message, 'data' => $this->present($connection)];
    }

    private function emptyState(): array
    {
        return [
            'status' => Status::NOT_CONFIGURED->value,
            'status_label' => Status::NOT_CONFIGURED->label(),
            'calendar_id' => null,
            'calendar_summary' => null,
            'calendar_timezone' => null,
            'last_connection_at' => null,
            'last_sync_at' => null,
            'last_error' => null,
        ];
    }

    // Solo datos no sensibles: nunca se exponen IDs externos ni credenciales.
    private function present(GoogleCalendarConnection $connection): array
    {
        return [
            'status' => $connection->status->value,
            'status_label' => $connection->status->label(),
            'calendar_id' => $connection->calendar_id,
            'calendar_summary' => $connection->calendar_summary,
            'calendar_timezone' => $connection->calendar_timezone,
            'last_connection_at' => $connection->last_connection_at,
            'last_sync_at' => $connection->last_sync_at,
            'last_error' => $connection->last_error,
        ];
    }
}
