<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('external_integration_id')->nullable();
            $table->string('name', 190)->nullable();
            $table->enum('status', ['not_configured', 'pending', 'connected', 'disconnected', 'error'])->default('not_configured');
            $table->string('calendar_id', 190)->default('primary');
            $table->string('calendar_summary', 255)->nullable();
            $table->string('calendar_timezone', 60)->nullable();
            $table->json('scopes_requested')->nullable();
            $table->json('scopes_granted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_connection_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadatos')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index('status');
            $table->index('external_integration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_connections');
    }
};
