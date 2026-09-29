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

        DB::statement('ALTER TABLE `parcels` ADD CONSTRAINT `parcels_dimensions_all_or_none` CHECK ((((`width_cm` is null) and (`length_cm` is null) and (`height_cm` is null)) or ((`width_cm` > 0) and (`length_cm` > 0) and (`height_cm` > 0))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `parcels_catalog_insert` BEFORE INSERT ON `parcels` FOR EACH ROW BEGIN IF NEW.current_status IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.current_status AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `parcels_catalog_update` BEFORE UPDATE ON `parcels` FOR EACH ROW BEGIN IF NEW.current_status IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.current_status AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `parcels` DROP CHECK `parcels_dimensions_all_or_none`');
        DB::unprepared('DROP TRIGGER IF EXISTS `parcels_catalog_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `parcels_catalog_update`');
    }
};
