<?php

return [
    'timezone' => env(
        'GK_SERVICIOS_TIMEZONE',
        'America/Argentina/Buenos_Aires'
    ),

    'route_prefix' => env('GOOGLE_CALENDAR_ROUTE_PREFIX', '/home'),

    'default_company_id' => (int) env('GOOGLE_CALENDAR_DEFAULT_COMPANY_ID', 1),

    'models' => [
        'company' => env('GOOGLE_CALENDAR_COMPANY_MODEL', 'App\\Models\\System\\Company'),
        'user' => env('GOOGLE_CALENDAR_USER_MODEL', 'App\\Models\\Generic\\User'),
        'agenda_event' => env('GOOGLE_CALENDAR_AGENDA_EVENT_MODEL', 'App\\Models\\System\\AgendaEvent'),
    ],

    'middleware' => [
        'user' => ['web', 'auth', 'verified', 'estadoverificar', 'blockip', 'isnotclient'],
        'admin' => ['web', 'auth', 'verified', 'estadoverificar', 'blockip', 'isnotclient', 'ensure.root'],
    ],

    'gkservicios' => [
        'base_url' => rtrim((string) env('GK_SERVICIOS_URL', 'https://gkservicios.gk-code.com/api/v1'), '/'),
        'registration_token' => env('GK_SERVICIOS_REGISTRATION_TOKEN'),
        'google_calendar_provider_id' => (int) env('GK_SERVICIOS_GOOGLE_PROVIDER_ID', 1),
        'google_calendar_service_id' => (int) env('GK_SERVICIOS_GOOGLE_CALENDAR_SERVICE_ID', 1),
        'timezone' => env('GK_SERVICIOS_TIMEZONE', 'America/Argentina/Buenos_Aires'),
    ],
];
