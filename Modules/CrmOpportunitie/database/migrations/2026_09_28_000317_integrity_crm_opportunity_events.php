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

        DB::statement('ALTER TABLE `crm_opportunity_events` ADD CONSTRAINT `crm_opportunity_events_from_group_check` CHECK ((((`from_funnel_id` is null) and (`from_step_id` is null) and (`from_funnel_code` is null) and (`from_funnel_title` is null) and (`from_step_code` is null) and (`from_step_title` is null) and (`from_outcome_type` is null)) or ((`from_funnel_id` is not null) and (`from_step_id` is not null) and (`from_funnel_code` is not null) and (`from_funnel_title` is not null) and (`from_step_code` is not null) and (`from_step_title` is not null) and (`from_outcome_type` is not null))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_opportunity_events_step_funnel_insert` BEFORE INSERT ON `crm_opportunity_events` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_sales_funnel_steps s WHERE s.id=NEW.to_step_id AND s.hq_id=NEW.hq_id AND s.funnel_id=NEW.to_funnel_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='to_step_id must belong to to_funnel_id'; END IF; IF NEW.from_step_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_funnel_steps s WHERE s.id=NEW.from_step_id AND s.hq_id=NEW.hq_id AND s.funnel_id=NEW.from_funnel_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='from_step_id must belong to from_funnel_id'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_opportunity_events_prevent_update` BEFORE UPDATE ON `crm_opportunity_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'crm_opportunity_events is append-only'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_opportunity_events_prevent_delete` BEFORE DELETE ON `crm_opportunity_events` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'crm_opportunity_events is append-only'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_opportunity_events_prevent_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_opportunity_events_prevent_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_opportunity_events_step_funnel_insert`');
        DB::statement('ALTER TABLE `crm_opportunity_events` DROP CHECK `crm_opportunity_events_from_group_check`');
    }
};
