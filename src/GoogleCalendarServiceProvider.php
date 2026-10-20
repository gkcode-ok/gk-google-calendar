<?php

namespace GKCode\GoogleCalendar;

use GKCode\GoogleCalendar\Models\GkServiciosTenant;
use Illuminate\Support\ServiceProvider;

class GoogleCalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/google-calendar.php',
            'google-calendar'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/google-calendar.php' => config_path('google-calendar.php'),
        ], 'google-calendar-config');

        $this->loadRoutesFrom(
            __DIR__ . '/../routes/GoogleCalendar/web.php'
        );

        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );

        $companyClass = config('google-calendar.models.company', 'App\\Models\\System\\Company');
        if (is_string($companyClass) && class_exists($companyClass) && method_exists($companyClass, 'resolveRelationUsing')) {
            $companyClass::resolveRelationUsing(
                'gkServiciosTenant',
                fn ($company) => $company->hasOne(GkServiciosTenant::class, 'company_id')
            );
        }
    }
}
