<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gk_servicios_settings') || Schema::hasColumn('gk_servicios_settings', 'google_provider_id')) {
            return;
        }

        Schema::table('gk_servicios_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('google_provider_id')->nullable()->after('registration_token');
            $table->unsignedBigInteger('google_calendar_service_id')->nullable()->after('google_provider_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('gk_servicios_settings') || !Schema::hasColumn('gk_servicios_settings', 'google_provider_id')) {
            return;
        }

        Schema::table('gk_servicios_settings', function (Blueprint $table) {
            $table->dropColumn(['google_provider_id', 'google_calendar_service_id']);
        });
    }
};
