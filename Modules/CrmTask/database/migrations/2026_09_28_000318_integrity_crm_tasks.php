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

        DB::statement('ALTER TABLE `crm_tasks` ADD CONSTRAINT `crm_tasks_completion_consistency` CHECK ((((`status` = _utf8mb4\'COMPLETED\') and (`completed_at` is not null) and (`completion_result` is not null)) or ((`status` <> _utf8mb4\'COMPLETED\') and (`completed_at` is null))))');
        DB::statement('ALTER TABLE `crm_tasks` ADD CONSTRAINT `crm_tasks_reminder_owner_check` CHECK (((`remind_at` is null) or (`assignee_id` is not null)))');
        DB::statement('ALTER TABLE `crm_tasks` ADD CONSTRAINT `crm_tasks_closed_reminder_check` CHECK (((`status` not in (_utf8mb4\'COMPLETED\',_utf8mb4\'CANCELLED\')) or (`remind_at` is null)))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_tasks_opportunity_customer_insert` BEFORE INSERT ON `crm_tasks` FOR EACH ROW BEGIN IF NEW.opportunity_id IS NOT NULL AND NEW.customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_opportunities o WHERE o.id=NEW.opportunity_id AND o.hq_id=NEW.hq_id AND o.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Task customer must match the opportunity customer'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_tasks_opportunity_customer_update` BEFORE UPDATE ON `crm_tasks` FOR EACH ROW BEGIN IF NEW.opportunity_id IS NOT NULL AND NEW.customer_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_opportunities o WHERE o.id=NEW.opportunity_id AND o.hq_id=NEW.hq_id AND o.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Task customer must match the opportunity customer'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_tasks_opportunity_customer_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_tasks_opportunity_customer_update`');
        DB::statement('ALTER TABLE `crm_tasks` DROP CHECK `crm_tasks_closed_reminder_check`');
        DB::statement('ALTER TABLE `crm_tasks` DROP CHECK `crm_tasks_reminder_owner_check`');
        DB::statement('ALTER TABLE `crm_tasks` DROP CHECK `crm_tasks_completion_consistency`');
    }
};
