<?php

namespace GKCode\GoogleCalendar;

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
            __DIR__ . '/../routes/googleCalendarRoute/web.php'
        );

        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );
    }
}