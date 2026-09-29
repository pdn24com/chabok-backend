<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifest_parcels', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('manifest_id');
            $table->unsignedInteger('parcel_id');
            $table->string('source_status', 12)->nullable();
            $table->unsignedInteger('origin_node_id')->nullable();
            $table->unsignedInteger('destination_node_id')->nullable();
            $table->unsignedInteger('route_plan_id')->nullable();
            $table->unsignedInteger('route_definition_version_id')->nullable();
            $table->unsignedInteger('route_plan_leg_id')->nullable();
            $table->unsignedInteger('route_definition_version_leg_id')->nullable();
            $table->unsignedInteger('assigned_driver_id')->nullable();
            $table->unsignedInteger('assigned_vehicle_id')->nullable();
            $table->enum('manifest_parcel_status', ['PENDING', 'VALIDATED', 'SUCCEEDED', 'FAILED', 'SKIPPED']);
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->enum('input_source', ['SCAN', 'MANUAL', 'BATCH', 'AWAITING']);
            $table->string('input_value', 64);
            $table->char('active_slot', 64)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('processed_at', 6)->nullable();
            $table->timestamp('evidence_recorded_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['manifest_id', 'parcel_id'], 'manifest_parcels_pair_unique');
            $table->unique(['active_slot'], 'manifest_parcels_active_slot_unique');
            $table->index(['hq_id', 'parcel_id'], 'manifest_parcels_parcel_fk');
            $table->index(['created_by'], 'manifest_parcels_created_by_foreign');
            $table->index(['hq_id', 'manifest_id', 'manifest_parcel_status'], 'manifest_parcels_bucket_index');
            $table->index(['hq_id', 'origin_node_id'], 'manifest_parcels_origin_fk');
            $table->index(['hq_id', 'destination_node_id'], 'manifest_parcels_destination_fk');
            $table->index(['hq_id', 'route_plan_id'], 'manifest_parcels_route_plan_fk');
            $table->index(['hq_id', 'route_definition_version_id'], 'manifest_parcels_route_version_fk');
            $table->index(['hq_id', 'route_plan_leg_id'], 'manifest_parcels_route_leg_fk');
            $table->index(['hq_id', 'assigned_driver_id'], 'manifest_parcels_driver_fk');
            $table->index(['hq_id', 'assigned_vehicle_id'], 'manifest_parcels_vehicle_fk');
            $table->index(['hq_id', 'route_definition_version_leg_id', 'manifest_parcel_status'], 'manifest_parcels_physical_leg_index');
            $table->foreign(['created_by'], 'manifest_parcels_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'assigned_driver_id'], 'manifest_parcels_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
            $table->foreign(['hq_id', 'assigned_vehicle_id'], 'manifest_parcels_vehicle_fk')->references(['hq_id', 'id'])->on('vehicles')->onDelete('restrict');
            $table->foreign(['hq_id', 'destination_node_id'], 'manifest_parcels_destination_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'manifest_id'], 'manifest_parcels_manifest_fk')->references(['hq_id', 'id'])->on('manifests')->onDelete('restrict');
            $table->foreign(['hq_id', 'origin_node_id'], 'manifest_parcels_origin_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'parcel_id'], 'manifest_parcels_parcel_fk')->references(['hq_id', 'id'])->on('parcels')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_id'], 'manifest_parcels_route_version_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_leg_id'], 'manifest_parcels_route_version_leg_fk')->references(['hq_id', 'id'])->on('route_definition_version_legs')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_id'], 'manifest_parcels_route_plan_fk')->references(['hq_id', 'id'])->on('route_plans')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_leg_id'], 'manifest_parcels_route_leg_fk')->references(['hq_id', 'id'])->on('route_plan_legs')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifest_parcels');
    }
};
