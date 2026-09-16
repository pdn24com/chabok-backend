<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_plans', function (Blueprint $table): void {
            $table->char('route_plan_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('route_definition_id', 36);
            $table->enum('status', ['PLANNED', 'IN_PROGRESS', 'COMPLETED', 'SUPERSEDED'])->default('PLANNED');
            $table->char('active_slot', 64)->nullable()->unique();
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 36);
            $table->timestamps(6);
            $table->foreign(['hq_id', 'consignment_id'], 'route_plans_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_id'], 'route_plans_definition_fk')->references(['hq_id', 'route_definition_id'])->on('route_definitions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'route_plan_id'], 'route_plans_hq_id_unique');
            $table->index(['hq_id', 'status', 'created_at'], 'route_plans_status_index');
        });

        Schema::create('route_plan_legs', function (Blueprint $table): void {
            $table->char('route_plan_leg_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('route_plan_id', 36);
            $table->char('source_route_definition_leg_id', 36);
            $table->unsignedSmallInteger('leg_order');
            $table->char('origin_node_id', 36);
            $table->char('destination_node_id', 36);
            $table->enum('status', ['PENDING', 'ROUTED', 'OUTBOUND_CONFIRMED', 'IN_TRANSIT', 'ARRIVED', 'RECEIVED']);
            $table->timestamp('routed_at', 6)->nullable();
            $table->timestamp('received_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'route_plan_id'], 'route_plan_legs_plan_fk')->references(['hq_id', 'route_plan_id'])->on('route_plans')->restrictOnDelete();
            $table->foreign(['hq_id', 'origin_node_id'], 'route_plan_legs_origin_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'route_plan_legs_destination_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['route_plan_id', 'leg_order'], 'route_plan_legs_order_unique');
            $table->unique(['hq_id', 'route_plan_leg_id'], 'route_plan_legs_hq_id_unique');
            $table->index(['hq_id', 'route_plan_id', 'status', 'leg_order'], 'route_plan_legs_progress_index');
        });

        Schema::create('transport_runs', function (Blueprint $table): void {
            $table->char('transport_run_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('transport_run_number', 40);
            $table->char('route_plan_leg_id', 36);
            $table->char('origin_node_id', 36);
            $table->char('destination_node_id', 36);
            $table->char('driver_id', 36);
            $table->char('vehicle_id', 36);
            $table->enum('status', ['CREATED', 'LOADED', 'DEPARTED', 'ARRIVED', 'CLOSED']);
            $table->unsignedInteger('version')->default(1);
            $table->char('created_by', 36);
            $table->timestamp('departed_at', 6)->nullable();
            $table->timestamp('arrived_at', 6)->nullable();
            $table->timestamp('closed_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['hq_id', 'route_plan_leg_id'], 'transport_runs_leg_fk')->references(['hq_id', 'route_plan_leg_id'])->on('route_plan_legs')->restrictOnDelete();
            $table->foreign(['hq_id', 'origin_node_id'], 'transport_runs_origin_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'transport_runs_destination_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'driver_id'], 'transport_runs_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['hq_id', 'vehicle_id'], 'transport_runs_vehicle_fk')->references(['hq_id', 'vehicle_id'])->on('vehicles')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'transport_run_number'], 'transport_runs_hq_number_unique');
            $table->unique(['hq_id', 'transport_run_id'], 'transport_runs_hq_id_unique');
            $table->index(['hq_id', 'origin_node_id', 'status', 'created_at'], 'transport_runs_origin_status_index');
            $table->index(['hq_id', 'destination_node_id', 'status'], 'transport_runs_destination_status_index');
        });

        Schema::create('transport_run_parcels', function (Blueprint $table): void {
            $table->char('transport_run_parcel_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('transport_run_id', 36);
            $table->char('parcel_id', 36);
            $table->timestamp('loaded_at', 6);
            $table->char('loaded_by', 36);
            $table->foreign(['hq_id', 'transport_run_id'], 'transport_run_parcels_run_fk')->references(['hq_id', 'transport_run_id'])->on('transport_runs')->restrictOnDelete();
            $table->foreign(['hq_id', 'parcel_id'], 'transport_run_parcels_parcel_fk')->references(['hq_id', 'parcel_id'])->on('parcels')->restrictOnDelete();
            $table->foreign('loaded_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['transport_run_id', 'parcel_id'], 'transport_run_parcels_pair_unique');
            $table->index(['hq_id', 'parcel_id'], 'transport_run_parcels_parcel_index');
        });

        Schema::table('parcels', function (Blueprint $table): void {
            $table->char('active_route_plan_id', 36)->nullable()->after('current_custodian_id');
            $table->char('active_route_plan_leg_id', 36)->nullable()->after('active_route_plan_id');
            $table->char('active_transport_run_id', 36)->nullable()->after('active_route_plan_leg_id');
            $table->foreign(['hq_id', 'active_route_plan_id'], 'parcels_active_route_plan_fk')->references(['hq_id', 'route_plan_id'])->on('route_plans')->restrictOnDelete();
            $table->foreign(['hq_id', 'active_route_plan_leg_id'], 'parcels_active_route_leg_fk')->references(['hq_id', 'route_plan_leg_id'])->on('route_plan_legs')->restrictOnDelete();
            $table->foreign(['hq_id', 'active_transport_run_id'], 'parcels_active_transport_run_fk')->references(['hq_id', 'transport_run_id'])->on('transport_runs')->restrictOnDelete();
            $table->index(['hq_id', 'active_route_plan_id', 'active_route_plan_leg_id'], 'parcels_active_route_index');
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table): void {
            $table->dropForeign('parcels_active_route_plan_fk');
            $table->dropForeign('parcels_active_route_leg_fk');
            $table->dropForeign('parcels_active_transport_run_fk');
            $table->dropIndex('parcels_active_route_index');
            $table->dropColumn(['active_route_plan_id', 'active_route_plan_leg_id', 'active_transport_run_id']);
        });
        Schema::dropIfExists('transport_run_parcels');
        Schema::dropIfExists('transport_runs');
        Schema::dropIfExists('route_plan_legs');
        Schema::dropIfExists('route_plans');
    }
};
