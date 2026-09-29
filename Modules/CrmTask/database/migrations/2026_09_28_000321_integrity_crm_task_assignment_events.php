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

        DB::statement('ALTER TABLE `crm_task_assignment_events` ADD CONSTRAINT `crm_task_assignment_events_reason_check` CHECK (((`event_type` not in (_utf8mb4\'REFER\',_utf8mb4\'REASSIGN\',_utf8mb4\'MEMBERSHIP_CHANGE\')) or (`reason` is not null)))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_task_assignment_events_prevent_update` BEFORE UPDATE ON `crm_task_assignment_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'crm_task_assignment_events is append-only'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_task_assignment_events_prevent_delete` BEFORE DELETE ON `crm_task_assignment_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'crm_task_assignment_events is append-only'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_task_assignment_events_prevent_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_task_assignment_events_prevent_update`');
        DB::statement('ALTER TABLE `crm_task_assignment_events` DROP CHECK `crm_task_assignment_events_reason_check`');
    }
};
