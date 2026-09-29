<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_rate_rules', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tariff_version_id');
            $table->unsignedInteger('service_offering_version_id')->nullable();
            $table->unsignedInteger('service_option_version_id')->nullable();
            $table->unsignedInteger('charge_type_id');
            $table->unsignedInteger('origin_zone_id')->nullable();
            $table->unsignedInteger('destination_zone_id')->nullable();
            $table->enum('calculation_method', ['FIXED', 'PER_UNIT', 'SLAB', 'TIERED', 'PERCENT', 'MIN_MAX']);
            $table->string('basis', 80)->default('BILLABLE_WEIGHT');
            $table->decimal('range_from', 16, 4)->nullable();
            $table->decimal('range_to', 16, 4)->nullable();
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->decimal('unit_rate', 18, 6)->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->unsignedBigInteger('minimum_amount')->nullable();
            $table->unsignedBigInteger('maximum_amount')->nullable();
            $table->enum('amount_rounding_mode', ['NONE', 'CEIL', 'FLOOR', 'HALF_UP'])->default('NONE');
            $table->unsignedBigInteger('amount_rounding_step')->nullable();
            $table->json('basis_charge_codes')->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedSmallInteger('priority')->default('100');
            $table->string('matrix_cell_id', 64)->nullable();
            $table->boolean('taxable')->nullable();
            $table->decimal('incremental_step_kg', 12, 4)->nullable();
            $table->decimal('incremental_step', 16, 4)->nullable();
            $table->unique(['tariff_version_id', 'matrix_cell_id'], 'tariff_matrix_cell_unique');
            $table->index(['service_offering_version_id'], 'tariff_rate_rules_service_offering_version_id_foreign');
            $table->index(['charge_type_id'], 'tariff_rate_rules_charge_type_id_foreign');
            $table->index(['origin_zone_id'], 'tariff_rate_rules_origin_zone_id_foreign');
            $table->index(['destination_zone_id'], 'tariff_rate_rules_destination_zone_id_foreign');
            $table->index(['tariff_version_id', 'service_offering_version_id', 'origin_zone_id', 'destination_zone_id', 'priority'], 'tariff_rule_lookup_index');
            $table->index(['service_option_version_id'], 'tariff_rule_service_option_fk');
            $table->index(['tariff_version_id', 'service_offering_version_id', 'service_option_version_id', 'priority'], 'tariff_rule_option_lookup_index');
            $table->foreign(['charge_type_id'], 'tariff_rate_rules_charge_type_id_foreign')->references(['id'])->on('pricing_charge_types')->onDelete('restrict');
            $table->foreign(['destination_zone_id'], 'tariff_rate_rules_destination_zone_id_foreign')->references(['id'])->on('pricing_zones')->onDelete('restrict');
            $table->foreign(['origin_zone_id'], 'tariff_rate_rules_origin_zone_id_foreign')->references(['id'])->on('pricing_zones')->onDelete('restrict');
            $table->foreign(['service_offering_version_id'], 'tariff_rate_rules_service_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
            $table->foreign(['service_option_version_id'], 'tariff_rule_service_option_fk')->references(['id'])->on('service_option_versions')->onDelete('restrict');
            $table->foreign(['tariff_version_id'], 'tariff_rate_rules_tariff_version_id_foreign')->references(['id'])->on('tariff_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_rate_rules');
    }
};
