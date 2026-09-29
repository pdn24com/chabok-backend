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
CREATE TRIGGER `crm_membership_events_prevent_update` BEFORE UPDATE ON `crm_membership_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'crm_membership_events is append-only'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_membership_events_prevent_delete` BEFORE DELETE ON `crm_membership_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'crm_membership_events is append-only'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_membership_events_prevent_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_membership_events_prevent_delete`');
    }
};
