<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `manifests` ADD CONSTRAINT `manifests_close_consistency` CHECK ((((`state` = _utf8mb4\'CLOSED\') and (`approved_by` is not null) and (`closed_at` is not null)) or ((`state` <> _utf8mb4\'CLOSED\') and (`approved_by` is null) and (`closed_at` is null))))');
        DB::statement('ALTER TABLE `manifests` ADD CONSTRAINT `manifests_operational_context_consistency` CHECK ((((`manifest_status` = _utf8mb4\'PD\') and (`manifest_type` = _utf8mb4\'PICKUP_ASSIGNMENT\') and (`operational_context_type` = _utf8mb4\'PICKUP_ASSIGNMENT\')) or ((`manifest_status` = _utf8mb4\'PU\') and (`manifest_type` = _utf8mb4\'PICKUP_COMPLETION\') and (`operational_context_type` = _utf8mb4\'PICKUP_COMPLETION\')) or ((`manifest_status` = _utf8mb4\'NPU\') and (`manifest_type` = _utf8mb4\'PICKUP_EXCEPTION\') and (`operational_context_type` = _utf8mb4\'PICKUP_EXCEPTION\')) or ((`manifest_status` = _utf8mb4\'IR\') and (`manifest_type` = _utf8mb4\'INBOUND_RECEPTION\') and (`operational_context_type` in (_utf8mb4\'PICKUP_RECEPTION\',_utf8mb4\'MOVEMENT_RECEPTION\'))) or ((`manifest_status` = _utf8mb4\'ROU\') and (`manifest_type` = _utf8mb4\'ROUTE_REGISTRATION\') and (`operational_context_type` = _utf8mb4\'ROUTE_REGISTRATION\')) or ((`manifest_status` = _utf8mb4\'OF\') and (`manifest_type` = _utf8mb4\'OUTBOUND_TRANSFER\') and (`operational_context_type` = _utf8mb4\'OUTBOUND_CONFIRMATION\')) or ((`manifest_status` = _utf8mb4\'OS\') and (`manifest_type` = _utf8mb4\'LINEHAUL_DEPARTURE\') and (`operational_context_type` = _utf8mb4\'LINEHAUL_DEPARTURE\')) or ((`manifest_status` = _utf8mb4\'CI\') and (`manifest_type` = _utf8mb4\'TRANSIT_UNLOAD\') and (`operational_context_type` = _utf8mb4\'TRANSIT_UNLOAD\')) or ((`manifest_status` = _utf8mb4\'OD\') and (`manifest_type` = _utf8mb4\'DELIVERY_ASSIGNMENT\') and (`operational_context_type` = _utf8mb4\'DELIVERY_ASSIGNMENT\')) or ((`manifest_status` = _utf8mb4\'OK\') and (`manifest_type` = _utf8mb4\'DELIVERY_COMPLETION\') and (`operational_context_type` = _utf8mb4\'DELIVERY_COMPLETION\')) or ((`manifest_status` = _utf8mb4\'NOK\') and (`manifest_type` = _utf8mb4\'DELIVERY_EXCEPTION\') and (`operational_context_type` = _utf8mb4\'DELIVERY_EXCEPTION\'))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `manifests_catalog_insert` BEFORE INSERT ON `manifests` FOR EACH ROW BEGIN IF NEW.manifest_status IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.manifest_status AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `manifests_catalog_update` BEFORE UPDATE ON `manifests` FOR EACH ROW BEGIN IF NEW.manifest_status IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.manifest_status AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `manifests` DROP CHECK `manifests_close_consistency`');
        DB::statement('ALTER TABLE `manifests` DROP CHECK `manifests_operational_context_consistency`');
        DB::unprepared('DROP TRIGGER IF EXISTS `manifests_catalog_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `manifests_catalog_update`');
    }
};
