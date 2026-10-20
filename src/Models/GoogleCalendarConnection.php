<?php

namespace GKCode\GoogleCalendar\Models;

use GKCode\GoogleCalendar\Enums\GoogleCalendarConnectionStatus;
use App\Models\Generic\User;
use App\Models\ModelGeneral;
use App\Models\System\Company;
use App\Traits\BelongsToCompany;

class GoogleCalendarConnection extends ModelGeneral
{
    use BelongsToCompany;

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
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isConnected(): bool
    {
        return $this->status === GoogleCalendarConnectionStatus::CONNECTED && $this->external_integration_id;
    }

    public static function generalConfig(string $titulo = 'Google Calendar', string $icon = 'mdi-google'): array
    {
        return parent::generalConfig($titulo, $icon);
    }
}
