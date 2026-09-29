<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_families', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id')->nullable();
            $table->string('owner_key', 36);
            $table->string('code', 80);
            $table->enum('purpose', ['SALES', 'PURCHASE', 'COMMISSION', 'INTERNAL_TRANSFER']);
            $table->enum('scope_type', ['PLATFORM', 'TENANT', 'SEGMENT', 'CUSTOMER', 'CONTRACT'])->default('TENANT');
            $table->string('scope_value', 120)->nullable();
            $table->char('currency', 3)->default('IRR');
            $table->unsignedSmallInteger('priority')->default('100');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->string('title', 200)->nullable();
            $table->enum('tariff_kind', ['FREIGHT', 'SERVICE'])->default('FREIGHT');
            $table->unsignedInteger('service_charge_type_id')->nullable();
            $table->unique(['owner_key', 'purpose', 'code'], 'tariff_family_owner_code_unique');
            $table->index(['created_by'], 'tariff_families_created_by_foreign');
            $table->index(['hq_id', 'purpose', 'scope_type', 'scope_value', 'priority'], 'tariff_family_resolution_index');
            $table->index(['service_charge_type_id'], 'tariff_families_service_charge_type_id_foreign');
            $table->foreign(['created_by'], 'tariff_families_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'tariff_families_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['service_charge_type_id'], 'tariff_families_service_charge_type_id_foreign')->references(['id'])->on('pricing_charge_types')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_families');
    }
};
