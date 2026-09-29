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
CREATE TRIGGER `parcel_custody_events_immutable_update` BEFORE UPDATE ON `parcel_custody_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable operational history'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `parcel_custody_events_immutable_delete` BEFORE DELETE ON `parcel_custody_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable operational history'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `parcel_custody_events_immutable_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `parcel_custody_events_immutable_delete`');
    }
};
