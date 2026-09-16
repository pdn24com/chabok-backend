<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TARGETS_WITH_CI = "'PD','PU','NPU','IR','ROU','OF','OS','CI','OD','OK','NOK'";
    private const TARGETS_WITHOUT_CI = "'PD','PU','NPU','IR','ROU','OF','OS','OD','OK','NOK'";
    private const TYPES_WITH_CI = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','INBOUND_RECEPTION','ROUTE_REGISTRATION','OUTBOUND_TRANSFER','LINEHAUL_DEPARTURE','TRANSIT_UNLOAD','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";
    private const TYPES_WITHOUT_CI = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','INBOUND_RECEPTION','ROUTE_REGISTRATION','OUTBOUND_TRANSFER','LINEHAUL_DEPARTURE','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";
    private const CONTEXTS_WITH_CI = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','PICKUP_RECEPTION','MOVEMENT_RECEPTION','ROUTE_REGISTRATION','OUTBOUND_CONFIRMATION','LINEHAUL_DEPARTURE','TRANSIT_UNLOAD','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";
    private const CONTEXTS_WITHOUT_CI = "'PICKUP_ASSIGNMENT','PICKUP_COMPLETION','PICKUP_EXCEPTION','PICKUP_RECEPTION','MOVEMENT_RECEPTION','ROUTE_REGISTRATION','OUTBOUND_CONFIRMATION','LINEHAUL_DEPARTURE','DELIVERY_ASSIGNMENT','DELIVERY_COMPLETION','DELIVERY_EXCEPTION'";
    private const OPERATIONAL_STATUSES_WITH_CI = "'D00','CFM','PD','PU','IR','ROU','OF','OS','CI','OD','OK','NPU','NOK','RH','RCH','RO','AA'";
    private const OPERATIONAL_STATUSES_WITHOUT_CI = "'D00','CFM','PD','PU','IR','ROU','OF','OS','OD','OK','NPU','NOK','RH','RCH','RO','AA'";

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE manifests DROP CHECK manifests_operational_context_consistency');
        DB::statement('ALTER TABLE manifests MODIFY manifest_status ENUM('.self::TARGETS_WITH_CI.') NOT NULL');
        DB::statement('ALTER TABLE manifests MODIFY manifest_type ENUM('.self::TYPES_WITH_CI.') NULL');
        DB::statement('ALTER TABLE manifests MODIFY operational_context_type ENUM('.self::CONTEXTS_WITH_CI.') NOT NULL');
        DB::statement('ALTER TABLE consignments MODIFY current_status ENUM('.self::OPERATIONAL_STATUSES_WITH_CI.') NOT NULL');
        DB::statement('ALTER TABLE parcels MODIFY current_status ENUM('.self::OPERATIONAL_STATUSES_WITH_CI.') NOT NULL');
        DB::statement('ALTER TABLE consignment_status_events MODIFY previous_status ENUM('.self::OPERATIONAL_STATUSES_WITH_CI.') NULL');
        DB::statement('ALTER TABLE consignment_status_events MODIFY new_status ENUM('.self::OPERATIONAL_STATUSES_WITH_CI.') NOT NULL');
        $this->addConsistencyConstraint();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (DB::table('manifests')->where('manifest_status', 'CI')->exists()
            || DB::table('parcels')->where('current_status', 'CI')->exists()
            || DB::table('consignments')->where('current_status', 'CI')->exists()
            || DB::table('consignment_status_events')
                ->where('previous_status', 'CI')->orWhere('new_status', 'CI')->exists()) {
            throw new RuntimeException('CI Manifest evidence exists; forward recovery is required before this migration can be rolled back.');
        }

        DB::statement('ALTER TABLE manifests DROP CHECK manifests_operational_context_consistency');
        DB::statement('ALTER TABLE manifests MODIFY manifest_status ENUM('.self::TARGETS_WITHOUT_CI.') NOT NULL');
        DB::statement('ALTER TABLE manifests MODIFY manifest_type ENUM('.self::TYPES_WITHOUT_CI.') NULL');
        DB::statement('ALTER TABLE manifests MODIFY operational_context_type ENUM('.self::CONTEXTS_WITHOUT_CI.') NOT NULL');
        DB::statement('ALTER TABLE consignments MODIFY current_status ENUM('.self::OPERATIONAL_STATUSES_WITHOUT_CI.') NOT NULL');
        DB::statement('ALTER TABLE parcels MODIFY current_status ENUM('.self::OPERATIONAL_STATUSES_WITHOUT_CI.') NOT NULL');
        DB::statement('ALTER TABLE consignment_status_events MODIFY previous_status ENUM('.self::OPERATIONAL_STATUSES_WITHOUT_CI.') NULL');
        DB::statement('ALTER TABLE consignment_status_events MODIFY new_status ENUM('.self::OPERATIONAL_STATUSES_WITHOUT_CI.') NOT NULL');
        DB::statement("ALTER TABLE manifests ADD CONSTRAINT manifests_operational_context_consistency CHECK ((manifest_status='PD' AND manifest_type='PICKUP_ASSIGNMENT' AND operational_context_type='PICKUP_ASSIGNMENT') OR (manifest_status='PU' AND manifest_type='PICKUP_COMPLETION' AND operational_context_type='PICKUP_COMPLETION') OR (manifest_status='NPU' AND manifest_type='PICKUP_EXCEPTION' AND operational_context_type='PICKUP_EXCEPTION') OR (manifest_status='IR' AND manifest_type='INBOUND_RECEPTION' AND operational_context_type IN ('PICKUP_RECEPTION','MOVEMENT_RECEPTION')) OR (manifest_status='ROU' AND manifest_type='ROUTE_REGISTRATION' AND operational_context_type='ROUTE_REGISTRATION') OR (manifest_status='OF' AND manifest_type='OUTBOUND_TRANSFER' AND operational_context_type='OUTBOUND_CONFIRMATION') OR (manifest_status='OS' AND manifest_type='LINEHAUL_DEPARTURE' AND operational_context_type='LINEHAUL_DEPARTURE') OR (manifest_status='OD' AND manifest_type='DELIVERY_ASSIGNMENT' AND operational_context_type='DELIVERY_ASSIGNMENT') OR (manifest_status='OK' AND manifest_type='DELIVERY_COMPLETION' AND operational_context_type='DELIVERY_COMPLETION') OR (manifest_status='NOK' AND manifest_type='DELIVERY_EXCEPTION' AND operational_context_type='DELIVERY_EXCEPTION'))");
    }

    private function addConsistencyConstraint(): void
    {
        DB::statement("ALTER TABLE manifests ADD CONSTRAINT manifests_operational_context_consistency CHECK ((manifest_status='PD' AND manifest_type='PICKUP_ASSIGNMENT' AND operational_context_type='PICKUP_ASSIGNMENT') OR (manifest_status='PU' AND manifest_type='PICKUP_COMPLETION' AND operational_context_type='PICKUP_COMPLETION') OR (manifest_status='NPU' AND manifest_type='PICKUP_EXCEPTION' AND operational_context_type='PICKUP_EXCEPTION') OR (manifest_status='IR' AND manifest_type='INBOUND_RECEPTION' AND operational_context_type IN ('PICKUP_RECEPTION','MOVEMENT_RECEPTION')) OR (manifest_status='ROU' AND manifest_type='ROUTE_REGISTRATION' AND operational_context_type='ROUTE_REGISTRATION') OR (manifest_status='OF' AND manifest_type='OUTBOUND_TRANSFER' AND operational_context_type='OUTBOUND_CONFIRMATION') OR (manifest_status='OS' AND manifest_type='LINEHAUL_DEPARTURE' AND operational_context_type='LINEHAUL_DEPARTURE') OR (manifest_status='CI' AND manifest_type='TRANSIT_UNLOAD' AND operational_context_type='TRANSIT_UNLOAD') OR (manifest_status='OD' AND manifest_type='DELIVERY_ASSIGNMENT' AND operational_context_type='DELIVERY_ASSIGNMENT') OR (manifest_status='OK' AND manifest_type='DELIVERY_COMPLETION' AND operational_context_type='DELIVERY_COMPLETION') OR (manifest_status='NOK' AND manifest_type='DELIVERY_EXCEPTION' AND operational_context_type='DELIVERY_EXCEPTION'))");
    }
};
