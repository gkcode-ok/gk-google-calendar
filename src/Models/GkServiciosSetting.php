<?php

namespace GKCode\GoogleCalendar\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuracion global (una sola fila) de GK Servicios para este sistema.
 */
class GkServiciosSetting extends Model
{
    protected $table = 'gk_servicios_settings';

    protected $fillable = ['registration_token', 'google_provider_id', 'google_calendar_service_id'];

    protected $hidden = ['registration_token'];

    protected $casts = ['registration_token' => 'encrypted'];
}
