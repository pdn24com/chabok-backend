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

        DB::statement('ALTER TABLE `manifest_parcels` ADD CONSTRAINT `manifest_parcels_failure_consistency` CHECK ((((`manifest_parcel_status` = _utf8mb4\'FAILED\') and (`failure_code` is not null)) or ((`manifest_parcel_status` <> _utf8mb4\'FAILED\') and (`failure_code` is null) and (`failure_reason` is null))))');
        DB::statement('ALTER TABLE `manifest_parcels` ADD CONSTRAINT `manifest_parcels_processed_consistency` CHECK ((((`manifest_parcel_status` in (_utf8mb4\'SUCCEEDED\',_utf8mb4\'FAILED\',_utf8mb4\'SKIPPED\')) and (`processed_at` is not null)) or ((`manifest_parcel_status` in (_utf8mb4\'PENDING\',_utf8mb4\'VALIDATED\')) and (`processed_at` is null))))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `manifest_parcels` DROP CHECK `manifest_parcels_failure_consistency`');
        DB::statement('ALTER TABLE `manifest_parcels` DROP CHECK `manifest_parcels_processed_consistency`');
    }
};
