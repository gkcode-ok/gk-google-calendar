<?php

namespace App\Models\Generic\GoogleCalendar;

use App\Models\ModelGeneral;
use App\Models\System\Company;

class GkServiciosTenant extends ModelGeneral
{
    protected $table = 'gk_servicios_tenants';

    protected $fillable = [
        'company_id',
        'name',
        'external_tenant_id',
        'client_id',
        'client_secret',
        'tenant_token',
        'status',
        'registered_at',
        'last_error',
    ];

    protected $hidden = ['client_id', 'client_secret', 'tenant_token'];

    protected $casts = [
        'company_id' => 'integer',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'tenant_token' => 'encrypted',
        'registered_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function hasCredentials(): bool
    {
        return $this->client_id && $this->client_secret && $this->tenant_token;
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled' && $this->hasCredentials();
    }

    public static function generalConfig(string $titulo = 'Habilitar Google Calendar', string $icon = 'mdi-calendar-sync'): array
    {
        return parent::generalConfig($titulo, $icon);
    }
}
