<?php

namespace GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar;

use GKCode\GoogleCalendar\Models\GkServiciosTenant;
use GKCode\GoogleCalendar\Services\GkServiciosTenantService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Administracion (solo ROOT) de las empresas habilitadas para Google Calendar.
 * Soporta proyectos multi-empresa (tabla companies) y mono-empresa.
 */
class GkServiciosTenantController extends BaseCalendarController
{
    protected $modelo = 'Habilitacion Google Calendar';
    protected $mainModel = GkServiciosTenant::class;

    public function __construct(private readonly GkServiciosTenantService $tenants)
    {
    }

    public function index(Request $request): array
    {
        $perPage = min(max((int) $request->query('totalpage', 15), 1), 100);

        if ($this->tenants->isMultiCompanyProject()) {
            $companyClass = config('google-calendar.models.company', 'App\\Models\\System\\Company');
            $query = $companyClass::query()
                ->with('gkServiciosTenant:id,company_id,status,registered_at,last_error')
                ->orderBy('name');

            if ($name = trim((string) $request->query('name', ''))) {
                $query->where('name', 'like', "%{$name}%");
            }

            if ($status = $request->query('status')) {
                $status === 'not_registered'
                    ? $query->whereDoesntHave('gkServiciosTenant')
                    : $query->whereHas('gkServiciosTenant', fn ($q) => $q->where('status', $status));
            }

            $page = $query->paginate($perPage);
            $page->getCollection()->transform(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug ?? Str::slug($company->name),
                'status' => $company->gkServiciosTenant->status ?? 'not_registered',
                'registered_at' => $company->gkServiciosTenant?->registered_at?->toDateTimeString(),
                'last_error' => $company->gkServiciosTenant->last_error ?? null,
            ]);
        } else {
            // Proyecto mono-empresa (sin tabla companies)
            $defaultId = (int) config('google-calendar.default_company_id', 1);
            $appName = (string) config('app.name', 'Sistema Principal');
            $tenant = GkServiciosTenant::where('company_id', $defaultId)->first();

            $items = collect([[
                'id' => $defaultId,
                'name' => $tenant->name ?? $appName,
                'slug' => Str::slug($appName),
                'status' => $tenant->status ?? 'not_registered',
                'registered_at' => $tenant?->registered_at?->toDateTimeString(),
                'last_error' => $tenant->last_error ?? null,
            ]]);

            $page = new LengthAwarePaginator($items, 1, $perPage, 1, ['path' => $request->url()]);
        }

        return [
            'status' => 0,
            'descripcion' => 'Empresas y estado de Google Calendar',
            'data' => $page,
            'configured' => $this->tenants->hasRegistrationConfig(),
            'catalog' => $this->tenants->catalogIds(),
            'generalConfig' => GkServiciosTenant::generalConfig(),
        ];
    }

    public function guardarToken(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'registration_token' => ['required', 'string', 'min:10', 'max:500'],
        ])->validate();

        $this->tenants->saveRegistrationToken($data['registration_token']);

        return ['status' => 0, 'descripcion' => 'Token de registro guardado.', 'configured' => true];
    }

    public function generarToken(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'admin_token' => ['required', 'string', 'min:10', 'max:500'],
            'name' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $result = $this->tenants->generateRegistrationToken($data['admin_token'], $data['name'] ?? config('app.name', 'Nexora'));
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => $result['error']];
        }
        return ['status' => 0, 'descripcion' => 'Token de registro generado y IDs del catalogo guardados.', 'configured' => true];
    }

    public function guardarCatalogo(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'provider_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $this->tenants->saveCatalogIds((int) $data['provider_id'], (int) $data['service_id']);

        return ['status' => 0, 'descripcion' => 'IDs del catalogo guardados.'];
    }

    public function habilitar(int $companyId): array
    {
        if ($this->tenants->isMultiCompanyProject()) {
            $companyClass = config('google-calendar.models.company', 'App\\Models\\System\\Company');
            $company = $companyClass::find($companyId);
            if (!$company) {
                return ['status' => -1, 'descripcion' => 'La empresa no existe.'];
            }
            $companyName = (string) $company->name;
        } else {
            $companyName = (string) config('app.name', 'Sistema Principal');
        }

        $result = $this->tenants->enableById($companyId, $companyName);
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Google Calendar habilitado para ' . $companyName . '.'];
    }

    public function deshabilitar(int $companyId): array
    {
        $companyName = $this->tenants->resolveCompanyName($companyId);
        if (!$this->tenants->disableById($companyId)) {
            return ['status' => -1, 'descripcion' => 'La empresa no tiene Google Calendar habilitado.'];
        }

        return ['status' => 0, 'descripcion' => 'Google Calendar deshabilitado para ' . $companyName . '.'];
    }
}
