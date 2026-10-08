<?php

namespace GKCode\GoogleCalendar\Services;

use GKCode\GoogleCalendar\Models\GkServiciosSetting;
use GKCode\GoogleCalendar\Models\GkServiciosTenant;
use App\Models\System\Company;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Alta y habilitacion de empresas (tenants) en GK Servicios.
 * Las credenciales del tenant se guardan cifradas por empresa, nunca en el .env.
 */
class GkServiciosTenantService
{
    public function hasRegistrationConfig(): bool
    {
        return rtrim((string) config('google-calendar.gkservicios.base_url'), '/') !== ''
            && $this->registrationToken() !== '';
    }

    // El token guardado desde el admin tiene prioridad; el .env queda como respaldo.
    public function registrationToken(): string
    {
        $stored = GkServiciosSetting::query()->value('registration_token');

        return (string) ($stored ?: config('google-calendar.gkservicios.registration_token'));
    }

    public function catalogIds(): array
    {
        $setting = GkServiciosSetting::query()->first();

        return [
            'provider_id' => (int) ($setting?->google_provider_id ?: config('google-calendar.gkservicios.google_calendar_provider_id')),
            'service_id' => (int) ($setting?->google_calendar_service_id ?: config('google-calendar.gkservicios.google_calendar_service_id')),
            'saved' => (bool) ($setting?->google_provider_id && $setting?->google_calendar_service_id),
        ];
    }

    public function saveCatalogIds(int $providerId, int $serviceId): void
    {
        $setting = GkServiciosSetting::query()->first() ?? new GkServiciosSetting();
        $setting->google_provider_id = $providerId;
        $setting->google_calendar_service_id = $serviceId;
        $setting->save();
    }

    public function saveRegistrationToken(string $token): void
    {
        $setting = GkServiciosSetting::query()->first() ?? new GkServiciosSetting();
        $setting->registration_token = trim($token);
        $setting->save();
    }

    /**
     * Pide a GK Servicios un token de registro para este sistema.
     * El token administrativo solo se usa en esta llamada y nunca se guarda.
     */
    public function generateRegistrationToken(string $adminToken, string $name = 'Nexora'): array
    {
        $catalog = $this->discoverCatalogIds($adminToken);
        if (!$catalog['ok']) {
            return ['ok' => false, 'error' => $catalog['error']];
        }

        $baseUrl = rtrim((string) config('google-calendar.gkservicios.base_url'), '/');
        if ($baseUrl === '') {
            return ['ok' => false, 'error' => 'Falta GK_SERVICIOS_URL en el .env.'];
        }

        try {
            $response = Http::acceptJson()->asJson()->timeout(20)
                ->withToken(trim($adminToken))
                ->withHeaders(['X-Request-ID' => (string) Str::uuid()])
                ->post($baseUrl . '/admin/registration-tokens', ['name' => $name]);
        } catch (ConnectionException $e) {
            return ['ok' => false, 'error' => 'No fue posible comunicarse con GK Servicios.'];
        }

        $json = (array) $response->json();
        $token = (string) ($json['data']['registration_token'] ?? '');

        if (!$response->successful() || $token === '') {
            Log::warning('GKServicios generar token de registro fallido', ['http_status' => $response->status(), 'code' => $json['error']['code'] ?? null]);

            return ['ok' => false, 'error' => $response->status() === 401
                ? 'GK Servicios rechazo el token administrativo.'
                : ($json['error']['message'] ?? $json['descripcion'] ?? 'Respuesta incompleta de GK Servicios.')];
        }

        $this->saveRegistrationToken($token);
        $this->saveCatalogIds($catalog['provider_id'], $catalog['service_id']);

        return ['ok' => true, 'error' => null];
    }

    /**
     * Consulta en GK Servicios los IDs del provider "google" y del service "calendar".
     */
    public function discoverCatalogIds(string $adminToken): array
    {
        $baseUrl = rtrim((string) config('google-calendar.gkservicios.base_url'), '/');
        $fail = fn (string $m) => ['ok' => false, 'error' => $m, 'provider_id' => 0, 'service_id' => 0];
        $http = fn () => Http::acceptJson()->timeout(20)->withToken(trim($adminToken))
            ->withHeaders(['X-Request-ID' => (string) Str::uuid()]);

        try {
            $providers = $http()->get($baseUrl . '/admin/integration-providers', ['per_page' => 100]);
            if ($providers->status() === 401) {
                return $fail('GK Servicios rechazo el token administrativo.');
            }
            $provider = $this->findBySlug($providers->json(), 'google');
            if (!$provider) {
                return $fail($providers->successful()
                    ? 'GK Servicios no tiene cargado el provider "google" en su catalogo (la lista esta vacia o no lo incluye). Debe darse de alta del lado de GK Servicios.'
                    : 'GK Servicios respondio HTTP ' . $providers->status() . ' al listar los providers.');
            }

            $services = $http()->get($baseUrl . '/admin/integration-services', ['provider_id' => $provider['id'], 'per_page' => 100]);
            $service = $this->findBySlug($services->json(), 'calendar');
            if (!$service) {
                return $fail('No se encontro el service "calendar" del provider google en GK Servicios.');
            }
        } catch (ConnectionException $e) {
            return $fail('No fue posible comunicarse con GK Servicios.');
        }

        return ['ok' => true, 'error' => null, 'provider_id' => (int) $provider['id'], 'service_id' => (int) $service['id']];
    }

    private function findBySlug(mixed $json, string $slug): ?array
    {
        $list = (array) $json;
        // Tolera respuestas con envoltorio (data / data.data / items).
        foreach (['data', 'data', 'items'] as $key) {
            if (isset($list[$key]) && is_array($list[$key])) {
                $list = $list[$key];
            }
        }

        foreach ($list as $item) {
            if (is_array($item) && ($item['slug'] ?? null) === $slug && isset($item['id'])) {
                return $item;
            }
        }

        return null;
    }

    public function enable(Company $company): array
    {
        $tenant = GkServiciosTenant::firstOrNew(['company_id' => $company->id]);

        // GK solo entrega las credenciales una vez; si ya existen se reutilizan.
        if ($tenant->hasCredentials()) {
            $tenant->status = 'enabled';
            $tenant->last_error = null;
            $tenant->save();

            return ['ok' => true, 'tenant' => $tenant, 'error' => null];
        }

        if (!$this->hasRegistrationConfig()) {
            return ['ok' => false, 'tenant' => $tenant, 'error' => 'GK Servicios no esta configurado en este servidor (URL o token de registro).'];
        }

        $requestId = (string) Str::uuid();

        try {
            $response = Http::acceptJson()->asJson()->timeout(20)
                ->withToken($this->registrationToken())
                ->withHeaders(['X-Request-ID' => $requestId])
                ->post(rtrim((string) config('google-calendar.gkservicios.base_url'), '/') . '/tenants/register', [
                    'name' => $company->name,
                    'external_id' => (string) $company->id,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('GKServicios registro sin conexion', ['company_id' => $company->id, 'request_id' => $requestId]);

            return $this->fail($tenant, $company, 'No fue posible comunicarse con GK Servicios.');
        }

        $json = (array) $response->json();
        $data = (array) ($json['data'] ?? []);

        if (!$response->successful() || empty($data['client_id']) || empty($data['client_secret']) || empty($data['tenant_token'])) {
            $code = $json['error']['code'] ?? null;
            $message = $json['error']['message'] ?? $json['descripcion'] ?? 'Respuesta incompleta de GK Servicios.';
            Log::warning('GKServicios registro fallido', [
                'company_id' => $company->id,
                'http_status' => $response->status(),
                'code' => $code,
                'request_id' => $json['error']['request_id'] ?? $requestId,
            ]);

            if ($code === 'TENANT_ALREADY_REGISTERED') {
                $message = 'La empresa ya esta registrada en GK Servicios y las credenciales no pueden recuperarse. Solicita su reinicio en GK Servicios.';
            }

            return $this->fail($tenant, $company, $message);
        }

        $tenant->fill([
            'name' => $company->name,
            'external_tenant_id' => (string) $company->id,
            'client_id' => $data['client_id'],
            'client_secret' => $data['client_secret'],
            'tenant_token' => $data['tenant_token'],
            'status' => 'enabled',
            'registered_at' => now(),
            'last_error' => null,
        ])->save();

        return ['ok' => true, 'tenant' => $tenant, 'error' => null];
    }

    public function disable(Company $company): bool
    {
        return GkServiciosTenant::where('company_id', $company->id)->update(['status' => 'disabled']) > 0;
    }

    public function forCompany(?int $companyId): ?GkServiciosTenant
    {
        if (!$companyId) {
            return null;
        }

        $tenant = GkServiciosTenant::where('company_id', $companyId)->first();

        return $tenant && $tenant->isEnabled() ? $tenant : null;
    }

    private function fail(GkServiciosTenant $tenant, Company $company, string $message): array
    {
        $tenant->name = $company->name;
        $tenant->external_tenant_id = (string) $company->id;
        $tenant->status = 'error';
        $tenant->last_error = $message;
        $tenant->save();

        return ['ok' => false, 'tenant' => $tenant, 'error' => $message];
    }
}
