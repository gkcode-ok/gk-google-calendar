<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_events', function (Blueprint $table) {
            $table->foreignId('google_calendar_connection_id')->nullable()->constrained('google_calendar_connections')->nullOnDelete();
            $table->string('google_calendar_id', 190)->nullable();
            $table->string('google_event_id', 190)->nullable();
            $table->string('google_event_html_link', 500)->nullable();
            $table->enum('google_sync_status', ['not_synced', 'pending', 'synced', 'error', 'deleted_remote'])->default('not_synced');
            $table->timestamp('google_synced_at')->nullable();
            $table->text('google_last_error')->nullable();
            $table->string('google_payload_hash', 64)->nullable();

            $table->index(['company_id', 'user_id', 'google_sync_status'], 'agenda_events_google_sync_idx');
            $table->index('google_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('agenda_events', function (Blueprint $table) {
            $table->dropIndex('agenda_events_google_sync_idx');
            $table->dropIndex(['google_event_id']);
            $table->dropConstrainedForeignId('google_calendar_connection_id');
            $table->dropColumn([
                'google_calendar_id',
                'google_event_id',
                'google_event_html_link',
                'google_sync_status',
                'google_synced_at',
                'google_last_error',
                'google_payload_hash',
            ]);
        });
    }
};
