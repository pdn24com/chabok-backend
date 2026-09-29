<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_rules', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('coverage_policy_version_id');
            $table->enum('target', ['PICKUP_SERVICE_AREA', 'DESTINATION_GATEWAY', 'LAST_MILE_NODE']);
            $table->unsignedInteger('target_node_id');
            $table->integer('priority')->default('0');
            $table->unsignedInteger('offering_version_id')->nullable();
            $table->enum('criterion_type', ['PROVINCE', 'CITY', 'POSTAL_RANGE', 'POLYGON', 'POINT_RADIUS']);
            $table->unsignedInteger('province_id')->nullable();
            $table->unsignedInteger('city_id')->nullable();
            $table->char('postal_code_from', 10)->nullable();
            $table->char('postal_code_to', 10)->nullable();
            $table->json('geometry_geojson')->nullable();
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'coverage_rules_hq_id_unique');
            $table->index(['hq_id', 'coverage_policy_version_id'], 'coverage_rules_version_fk');
            $table->index(['hq_id', 'target_node_id'], 'coverage_rules_target_node_fk');
            $table->index(['city_id'], 'coverage_rules_city_id_foreign');
            $table->index(['offering_version_id'], 'coverage_rules_offering_version_id_foreign');
            $table->index(['hq_id', 'target', 'priority', 'criterion_type'], 'coverage_rules_resolution_index');
            $table->index(['province_id', 'city_id'], 'coverage_rules_geography_index');
            $table->foreign(['city_id'], 'coverage_rules_city_id_foreign')->references(['id'])->on('cities')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_policy_version_id'], 'coverage_rules_version_fk')->references(['hq_id', 'id'])->on('coverage_policy_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'target_node_id'], 'coverage_rules_target_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['offering_version_id'], 'coverage_rules_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
            $table->foreign(['province_id'], 'coverage_rules_province_id_foreign')->references(['id'])->on('provinces')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_rules');
    }
};
