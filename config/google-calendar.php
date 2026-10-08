<?php

return [
    'timezone' => env(
        'GK_SERVICIOS_TIMEZONE',
        'America/Argentina/Buenos_Aires'
    ),

    'gkservicios' => [
        'base_url' => rtrim((string) env('GK_SERVICIOS_URL', 'https://gkservicios.gk-code.com/api/v1'), '/'),
        'registration_token' => env('GK_SERVICIOS_REGISTRATION_TOKEN'),
        'google_calendar_provider_id' => (int) env('GK_SERVICIOS_GOOGLE_PROVIDER_ID', 1),
        'google_calendar_service_id' => (int) env('GK_SERVICIOS_GOOGLE_CALENDAR_SERVICE_ID', 1),
        'timezone' => env('GK_SERVICIOS_TIMEZONE', 'America/Argentina/Buenos_Aires'),
    ],
];
