<?php

namespace GKCode\GoogleCalendar\Models;

use GKCode\GoogleCalendar\Enums\GoogleCalendarConnectionStatus;
use Illuminate\Database\Eloquent\Model;

class GoogleCalendarConnection extends Model
{
    protected $table = 'google_calendar_connections';

    protected $fillable = [
        'company_id',
        'user_id',
        'external_integration_id',
        'name',
        'status',
        'calendar_id',
        'calendar_summary',
        'calendar_timezone',
        'scopes_requested',
        'scopes_granted',
        'token_expires_at',
        'last_sync_at',
        'last_connection_at',
        'last_error',
        'metadatos',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'user_id' => 'integer',
        'external_integration_id' => 'integer',
        'status' => GoogleCalendarConnectionStatus::class,
        'scopes_requested' => 'array',
        'scopes_granted' => 'array',
        'metadatos' => 'array',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_connection_at' => 'datetime',
    ];

    public function company()
    {
        $companyClass = config('google-calendar.models.company', 'App\\Models\\System\\Company');
        if (class_exists($companyClass)) {
            return $this->belongsTo($companyClass, 'company_id');
        }

        return null;
    }

    public function user()
    {
        $userClass = config('google-calendar.models.user', 'App\\Models\\Generic\\User');
        if (!class_exists($userClass) && class_exists('App\\Models\\User')) {
            $userClass = 'App\\Models\\User';
        }

        return $this->belongsTo($userClass, 'user_id');
    }

    public function isConnected(): bool
    {
        return $this->status === GoogleCalendarConnectionStatus::CONNECTED && $this->external_integration_id;
    }

    public static function generalConfig(string $titulo = 'Google Calendar', string $icon = 'mdi-google'): array
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
