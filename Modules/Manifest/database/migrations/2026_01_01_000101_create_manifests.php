<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manifests', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('manifest_number', 32);
            $table->unsignedInteger('node_id');
            $table->unsignedInteger('origin_node_id')->nullable();
            $table->unsignedInteger('destination_node_id')->nullable();
            $table->unsignedInteger('route_plan_id')->nullable();
            $table->unsignedInteger('route_definition_version_id')->nullable();
            $table->unsignedInteger('route_plan_leg_id')->nullable();
            $table->unsignedInteger('route_definition_version_leg_id')->nullable();
            $table->unsignedInteger('source_manifest_id')->nullable();
            $table->string('manifest_status', 32);
            $table->enum('manifest_type', ['PICKUP_ASSIGNMENT', 'PICKUP_COMPLETION', 'PICKUP_EXCEPTION', 'INBOUND_RECEPTION', 'ROUTE_REGISTRATION', 'OUTBOUND_TRANSFER', 'LINEHAUL_DEPARTURE', 'TRANSIT_UNLOAD', 'DELIVERY_ASSIGNMENT', 'DELIVERY_COMPLETION', 'DELIVERY_EXCEPTION'])->nullable();
            $table->enum('operational_context_type', ['PICKUP_ASSIGNMENT', 'PICKUP_COMPLETION', 'PICKUP_EXCEPTION', 'PICKUP_RECEPTION', 'MOVEMENT_RECEPTION', 'ROUTE_REGISTRATION', 'OUTBOUND_CONFIRMATION', 'LINEHAUL_DEPARTURE', 'TRANSIT_UNLOAD', 'DELIVERY_ASSIGNMENT', 'DELIVERY_COMPLETION', 'DELIVERY_EXCEPTION']);
            $table->string('context_key', 200);
            $table->unsignedInteger('assigned_driver_id')->nullable();
            $table->unsignedInteger('assigned_vehicle_id')->nullable();
            $table->enum('state', ['DRAFT', 'OPEN', 'CLOSED']);
            $table->unsignedInteger('version')->default('1');
            $table->unsignedInteger('created_by');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('closed_at', 6)->nullable();
            $table->timestamp('operation_recorded_at', 6)->nullable();
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['manifest_number'], 'manifests_manifest_number_unique');
            $table->unique(['hq_id', 'id'], 'manifests_hq_id_unique');
            $table->index(['created_by'], 'manifests_created_by_foreign');
            $table->index(['approved_by'], 'manifests_approved_by_foreign');
            $table->index(['hq_id', 'node_id', 'state', 'created_at', 'id'], 'manifests_node_list_index');
            $table->index(['hq_id', 'manifest_status', 'state'], 'manifests_target_state_index');
            $table->index(['hq_id', 'origin_node_id'], 'manifests_origin_node_fk');
            $table->index(['hq_id', 'route_plan_id'], 'manifests_route_plan_fk');
            $table->index(['hq_id', 'assigned_driver_id'], 'manifests_assigned_driver_fk');
            $table->index(['hq_id', 'assigned_vehicle_id'], 'manifests_assigned_vehicle_fk');
            $table->index(['hq_id', 'destination_node_id', 'state'], 'manifests_destination_state_index');
            $table->index(['hq_id', 'route_plan_leg_id', 'manifest_type'], 'manifests_route_leg_type_index');
            $table->index(['hq_id', 'node_id', 'operational_context_type', 'state'], 'manifests_operational_context_index');
            $table->index(['hq_id', 'route_definition_version_id'], 'manifests_route_version_fk');
            $table->index(['hq_id', 'source_manifest_id'], 'manifests_source_manifest_index');
            $table->index(['hq_id', 'route_definition_version_leg_id', 'state'], 'manifests_physical_leg_index');
            $table->foreign(['approved_by'], 'manifests_approved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['created_by'], 'manifests_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'assigned_driver_id'], 'manifests_assigned_driver_fk')->references(['hq_id', 'id'])->on('drivers')->onDelete('restrict');
            $table->foreign(['hq_id', 'assigned_vehicle_id'], 'manifests_assigned_vehicle_fk')->references(['hq_id', 'id'])->on('vehicles')->onDelete('restrict');
            $table->foreign(['hq_id', 'destination_node_id'], 'manifests_destination_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'node_id'], 'manifests_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'origin_node_id'], 'manifests_origin_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_id'], 'manifests_route_version_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_leg_id'], 'manifests_route_version_leg_fk')->references(['hq_id', 'id'])->on('route_definition_version_legs')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_id'], 'manifests_route_plan_fk')->references(['hq_id', 'id'])->on('route_plans')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_leg_id'], 'manifests_route_plan_leg_fk')->references(['hq_id', 'id'])->on('route_plan_legs')->onDelete('restrict');
            $table->foreign(['hq_id', 'source_manifest_id'], 'manifests_source_manifest_fk')->references(['hq_id', 'id'])->on('manifests')->onDelete('restrict');
            $table->foreign(['hq_id'], 'manifests_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manifests');
    }
};
