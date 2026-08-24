<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TARGETS = "'PD','PU','NPU','IR','ROU','OF','OS','OD','OK','NOK'";
    private const TYPES = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','INBOUND_RECEPTION','ROUTE_REGISTRATION','OUTBOUND_TRANSFER','LINEHAUL_DEPARTURE','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";
    private const CONTEXTS = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','PICKUP_RECEPTION','MOVEMENT_RECEPTION','ROUTE_REGISTRATION','OUTBOUND_CONFIRMATION','LINEHAUL_DEPARTURE','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";
    private const COMPATIBLE_CONTEXTS = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','PICKUP_RECEPTION','TRANSPORT_RECEPTION','MOVEMENT_RECEPTION','ROUTE_REGISTRATION','ROUTE_OUTBOUND','OUTBOUND_CONFIRMATION','LINEHAUL_DEPARTURE','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE manifests DROP CHECK manifests_operational_context_consistency');
            DB::statement('ALTER TABLE manifests MODIFY manifest_status ENUM('.self::TARGETS.') NOT NULL');
            DB::statement('ALTER TABLE manifests MODIFY manifest_type ENUM('.self::TYPES.') NULL');
            DB::statement('ALTER TABLE manifests MODIFY operational_context_type ENUM('.self::COMPATIBLE_CONTEXTS.') NOT NULL');
            DB::table('manifests')->where('operational_context_type', 'TRANSPORT_RECEPTION')->update(['operational_context_type' => 'MOVEMENT_RECEPTION']);
            DB::table('manifests')->where('operational_context_type', 'ROUTE_OUTBOUND')->update(['operational_context_type' => 'OUTBOUND_CONFIRMATION']);
            DB::statement('ALTER TABLE manifests MODIFY operational_context_type ENUM('.self::CONTEXTS.') NOT NULL');
        }

        Schema::table('manifests', function (Blueprint $table): void {
            $table->string('context_key', 200)->nullable()->after('operational_context_type');
            $table->char('source_manifest_id', 36)->nullable()->after('route_plan_leg_id');
            $table->char('route_definition_version_id', 36)->nullable()->after('route_plan_id');
            $table->char('route_definition_version_leg_id', 36)->nullable()->after('route_plan_leg_id');
            $table->timestamp('operation_recorded_at', 6)->nullable()->after('closed_at');
            $table->foreign(['hq_id', 'source_manifest_id'], 'manifests_source_manifest_fk')->references(['hq_id', 'manifest_id'])->on('manifests')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_version_id'], 'manifests_route_version_fk')->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_version_leg_id'], 'manifests_route_version_leg_fk')->references(['hq_id', 'route_definition_version_leg_id'])->on('route_definition_version_legs')->restrictOnDelete();
            $table->index(['hq_id', 'source_manifest_id'], 'manifests_source_manifest_index');
            $table->index(['hq_id', 'route_definition_version_leg_id', 'state'], 'manifests_physical_leg_index');
        });

        DB::table('manifests')->whereNull('context_key')->update([
            'context_key' => DB::raw("CONCAT(operational_context_type, ':LEGACY:', manifest_id)"),
        ]);

        Schema::table('manifest_parcels', function (Blueprint $table): void {
            $table->string('source_status', 12)->nullable()->after('parcel_id');
            $table->char('origin_node_id', 36)->nullable()->after('source_status');
            $table->char('destination_node_id', 36)->nullable()->after('origin_node_id');
            $table->char('route_plan_id', 36)->nullable()->after('destination_node_id');
            $table->char('route_definition_version_id', 36)->nullable()->after('route_plan_id');
            $table->char('route_plan_leg_id', 36)->nullable()->after('route_definition_version_id');
            $table->char('route_definition_version_leg_id', 36)->nullable()->after('route_plan_leg_id');
            $table->char('assigned_driver_id', 36)->nullable()->after('route_definition_version_leg_id');
            $table->char('assigned_vehicle_id', 36)->nullable()->after('assigned_driver_id');
            $table->timestamp('evidence_recorded_at', 6)->nullable()->after('processed_at');
            $table->foreign(['hq_id', 'origin_node_id'], 'manifest_parcels_origin_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_node_id'], 'manifest_parcels_destination_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_plan_id'], 'manifest_parcels_route_plan_fk')->references(['hq_id', 'route_plan_id'])->on('route_plans')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_version_id'], 'manifest_parcels_route_version_fk')->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_plan_leg_id'], 'manifest_parcels_route_leg_fk')->references(['hq_id', 'route_plan_leg_id'])->on('route_plan_legs')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_version_leg_id'], 'manifest_parcels_route_version_leg_fk')->references(['hq_id', 'route_definition_version_leg_id'])->on('route_definition_version_legs')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_driver_id'], 'manifest_parcels_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_vehicle_id'], 'manifest_parcels_vehicle_fk')->references(['hq_id', 'vehicle_id'])->on('vehicles')->restrictOnDelete();
            $table->index(['hq_id', 'route_definition_version_leg_id', 'manifest_parcel_status'], 'manifest_parcels_physical_leg_index');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE manifests MODIFY context_key VARCHAR(200) NOT NULL');
            DB::statement("ALTER TABLE manifests ADD CONSTRAINT manifests_operational_context_consistency CHECK ((manifest_status='PD' AND manifest_type='PICKUP_ASSIGNMENT' AND operational_context_type='PICKUP_ASSIGNMENT') OR (manifest_status='PU' AND manifest_type='PICKUP_COMPLETION' AND operational_context_type='PICKUP_COMPLETION') OR (manifest_status='NPU' AND manifest_type='PICKUP_EXCEPTION' AND operational_context_type='PICKUP_EXCEPTION') OR (manifest_status='IR' AND manifest_type='INBOUND_RECEPTION' AND operational_context_type IN ('PICKUP_RECEPTION','MOVEMENT_RECEPTION')) OR (manifest_status='ROU' AND manifest_type='ROUTE_REGISTRATION' AND operational_context_type='ROUTE_REGISTRATION') OR (manifest_status='OF' AND manifest_type='OUTBOUND_TRANSFER' AND operational_context_type='OUTBOUND_CONFIRMATION') OR (manifest_status='OS' AND manifest_type='LINEHAUL_DEPARTURE' AND operational_context_type='LINEHAUL_DEPARTURE') OR (manifest_status='OD' AND manifest_type='DELIVERY_ASSIGNMENT' AND operational_context_type='DELIVERY_ASSIGNMENT') OR (manifest_status='OK' AND manifest_type='DELIVERY_COMPLETION' AND operational_context_type='DELIVERY_COMPLETION') OR (manifest_status='NOK' AND manifest_type='DELIVERY_EXCEPTION' AND operational_context_type='DELIVERY_EXCEPTION'))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE manifests DROP CHECK manifests_operational_context_consistency');
        }
        Schema::table('manifest_parcels', function (Blueprint $table): void {
            foreach (['manifest_parcels_origin_fk','manifest_parcels_destination_fk','manifest_parcels_route_plan_fk','manifest_parcels_route_version_fk','manifest_parcels_route_leg_fk','manifest_parcels_route_version_leg_fk','manifest_parcels_driver_fk','manifest_parcels_vehicle_fk'] as $foreign) $table->dropForeign($foreign);
            $table->dropIndex('manifest_parcels_physical_leg_index');
            $table->dropColumn(['source_status','origin_node_id','destination_node_id','route_plan_id','route_definition_version_id','route_plan_leg_id','route_definition_version_leg_id','assigned_driver_id','assigned_vehicle_id','evidence_recorded_at']);
        });
        Schema::table('manifests', function (Blueprint $table): void {
            foreach (['manifests_source_manifest_fk','manifests_route_version_fk','manifests_route_version_leg_fk'] as $foreign) $table->dropForeign($foreign);
            $table->dropIndex('manifests_source_manifest_index');
            $table->dropIndex('manifests_physical_leg_index');
            $table->dropColumn(['context_key','source_manifest_id','route_definition_version_id','route_definition_version_leg_id','operation_recorded_at']);
        });
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE manifests MODIFY operational_context_type ENUM('.self::COMPATIBLE_CONTEXTS.') NOT NULL');
            DB::table('manifests')->where('operational_context_type', 'MOVEMENT_RECEPTION')->update(['operational_context_type' => 'TRANSPORT_RECEPTION']);
            DB::table('manifests')->where('operational_context_type', 'OUTBOUND_CONFIRMATION')->update(['operational_context_type' => 'ROUTE_OUTBOUND']);
            DB::statement("ALTER TABLE manifests MODIFY manifest_status ENUM('IR','OF','OD') NOT NULL");
            DB::statement("ALTER TABLE manifests MODIFY manifest_type ENUM('INBOUND_RECEPTION','OUTBOUND_TRANSFER','DELIVERY_ASSIGNMENT') NULL");
            DB::statement("ALTER TABLE manifests MODIFY operational_context_type ENUM('PICKUP_RECEPTION','TRANSPORT_RECEPTION','ROUTE_OUTBOUND','DELIVERY_ASSIGNMENT') NOT NULL");
            DB::statement("ALTER TABLE manifests ADD CONSTRAINT manifests_operational_context_consistency CHECK ((manifest_status='IR' AND manifest_type='INBOUND_RECEPTION' AND operational_context_type IN ('PICKUP_RECEPTION','TRANSPORT_RECEPTION')) OR (manifest_status='OF' AND manifest_type='OUTBOUND_TRANSFER' AND operational_context_type='ROUTE_OUTBOUND') OR (manifest_status='OD' AND manifest_type='DELIVERY_ASSIGNMENT' AND operational_context_type='DELIVERY_ASSIGNMENT'))");
        }
    }
};
