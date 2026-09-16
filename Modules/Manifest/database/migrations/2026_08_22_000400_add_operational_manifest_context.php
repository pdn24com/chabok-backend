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
        Schema::table('manifests', function (Blueprint $table): void {
            $table->enum('manifest_type', ['INBOUND_RECEPTION', 'OUTBOUND_TRANSFER', 'DELIVERY_ASSIGNMENT'])->nullable()->after('manifest_status');
            $table->char('origin_node_id', 36)->nullable()->after('node_id');
            $table->char('destination_node_id', 36)->nullable()->after('origin_node_id');
            $table->char('route_plan_id', 36)->nullable()->after('destination_node_id');
            $table->char('route_plan_leg_id', 36)->nullable()->after('route_plan_id');
            $table->char('transport_run_id', 36)->nullable()->after('route_plan_leg_id');
            $table->char('assigned_vehicle_id', 36)->nullable()->after('assigned_driver_id');
            $table->foreign(['hq_id', 'origin_node_id'], 'manifests_origin_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'manifests_destination_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_plan_id'], 'manifests_route_plan_fk')->references(['hq_id', 'route_plan_id'])->on('route_plans')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_plan_leg_id'], 'manifests_route_plan_leg_fk')->references(['hq_id', 'route_plan_leg_id'])->on('route_plan_legs')->restrictOnDelete();
            $table->foreign(['hq_id', 'transport_run_id'], 'manifests_transport_run_fk')->references(['hq_id', 'transport_run_id'])->on('transport_runs')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_driver_id'], 'manifests_assigned_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_vehicle_id'], 'manifests_assigned_vehicle_fk')->references(['hq_id', 'vehicle_id'])->on('vehicles')->restrictOnDelete();
            $table->index(['hq_id', 'destination_node_id', 'state'], 'manifests_destination_state_index');
            $table->index(['hq_id', 'route_plan_leg_id', 'manifest_type'], 'manifests_route_leg_type_index');
        });
        DB::table('manifests')->where('manifest_status', 'IR')->update(['manifest_type' => 'INBOUND_RECEPTION']);
        DB::table('manifests')->where('manifest_status', 'OF')->update(['manifest_type' => 'OUTBOUND_TRANSFER']);
        DB::table('manifests')->where('manifest_status', 'OD')->update(['manifest_type' => 'DELIVERY_ASSIGNMENT']);
    }

    public function down(): void
    {
        Schema::table('manifests', function (Blueprint $table): void {
            foreach (['manifests_origin_node_fk', 'manifests_destination_node_fk', 'manifests_route_plan_fk', 'manifests_route_plan_leg_fk', 'manifests_transport_run_fk', 'manifests_assigned_driver_fk', 'manifests_assigned_vehicle_fk'] as $foreign) $table->dropForeign($foreign);
            $table->dropIndex('manifests_destination_state_index');
            $table->dropIndex('manifests_route_leg_type_index');
            $table->dropColumn(['manifest_type', 'origin_node_id', 'destination_node_id', 'route_plan_id', 'route_plan_leg_id', 'transport_run_id', 'assigned_vehicle_id']);
        });
    }
};
