<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gk_servicios_tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 190);
            $table->string('external_tenant_id', 190)->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('tenant_token')->nullable();
            $table->enum('status', ['enabled', 'disabled', 'error'])->default('disabled');
            $table->timestamp('registered_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gk_servicios_tenants');
    }
};
