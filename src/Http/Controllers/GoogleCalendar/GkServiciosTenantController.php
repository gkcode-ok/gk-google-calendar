<?php

namespace GKCode\GoogleCalendar\Http\Controllers\GoogleCalendar;

use App\Http\Controllers\BaseController;
use GKCode\GoogleCalendar\Models\GkServiciosTenant;
use App\Models\System\Company;
use GKCode\GoogleCalendar\Services\GkServiciosTenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Administracion (solo ROOT) de las empresas habilitadas para Google Calendar.
 */
class GkServiciosTenantController extends BaseController
{
    protected $modelo = 'Habilitacion Google Calendar';
    protected $mainModel = GkServiciosTenant::class;

    public function __construct(private readonly GkServiciosTenantService $tenants)
    {
    }

    public function index(Request $request): array
    {
        $perPage = min(max((int) $request->query('totalpage', 15), 1), 100);

        $query = Company::query()->with('gkServiciosTenant:id,company_id,status,registered_at,last_error')->orderBy('name');

        if ($name = trim((string) $request->query('name', ''))) {
            $query->where('name', 'like', "%{$name}%");
        }

        if ($status = $request->query('status')) {
            $status === 'not_registered'
                ? $query->whereDoesntHave('gkServiciosTenant')
                : $query->whereHas('gkServiciosTenant', fn ($q) => $q->where('status', $status));
        }

        $page = $query->paginate($perPage);
        $page->getCollection()->transform(fn (Company $company) => [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'status' => $company->gkServiciosTenant->status ?? 'not_registered',
            'registered_at' => $company->gkServiciosTenant?->registered_at?->toDateTimeString(),
            'last_error' => $company->gkServiciosTenant->last_error ?? null,
        ]);

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

        $result = $this->tenants->generateRegistrationToken($data['admin_token'], $data['name'] ?? 'Nexora');
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
        $company = Company::find($companyId);
        if (!$company) {
            return ['status' => -1, 'descripcion' => 'La empresa no existe.'];
        }

        $result = $this->tenants->enable($company);
        if (!$result['ok']) {
            return ['status' => -1, 'descripcion' => $result['error']];
        }

        return ['status' => 0, 'descripcion' => 'Google Calendar habilitado para ' . $company->name . '.'];
    }

    public function deshabilitar(int $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company || !$this->tenants->disable($company)) {
            return ['status' => -1, 'descripcion' => 'La empresa no tiene Google Calendar habilitado.'];
        }

        return ['status' => 0, 'descripcion' => 'Google Calendar deshabilitado para ' . $company->name . '.'];
    }
}
