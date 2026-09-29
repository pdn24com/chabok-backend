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

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `consignment_status_events_catalog_insert` BEFORE INSERT ON `consignment_status_events` FOR EACH ROW BEGIN IF NEW.previous_status IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.previous_status AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF; IF NEW.new_status IS NOT NULL AND NOT EXISTS (SELECT 1 FROM operational_statuses s WHERE s.code=NEW.new_status AND (s.hq_id IS NULL OR s.hq_id=NEW.hq_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unknown operational status for tenant'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `consignment_status_events_immutable_update` BEFORE UPDATE ON `consignment_status_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `consignment_status_events_immutable_delete` BEFORE DELETE ON `consignment_status_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_status_events_catalog_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_status_events_immutable_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_status_events_immutable_delete`');
    }
};
