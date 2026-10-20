<?php

namespace GKCode\GoogleCalendar\Models;

use Illuminate\Database\Eloquent\Model;

class GkServiciosTenant extends Model
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
        $companyClass = config('google-calendar.models.company', 'App\\Models\\System\\Company');
        if (class_exists($companyClass)) {
            return $this->belongsTo($companyClass, 'company_id');
        }

        return null;
    }

    public function hasCredentials(): bool
    {
        return !empty($this->client_id) && !empty($this->client_secret) && !empty($this->tenant_token);
    }

    public function isEnabled(): bool
    {
        return $this->status === 'enabled' && $this->hasCredentials();
    }

    public static function generalConfig(string $titulo = 'Habilitar Google Calendar', string $icon = 'mdi-calendar-sync'): array
    {
        return [
            'estado' => [
                ['id' => '', 'nombre' => 'Todos', 'color' => '', 'select' => true],
            ],
            'titulo' => $titulo,
            'icon' => $icon,
        ];
    }
}
