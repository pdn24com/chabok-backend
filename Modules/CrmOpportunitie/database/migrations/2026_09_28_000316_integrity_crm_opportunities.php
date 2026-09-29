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

        DB::statement('ALTER TABLE `crm_opportunities` ADD CONSTRAINT `crm_opportunities_probability_check` CHECK (((`probability` is null) or ((`probability` >= 0) and (`probability` <= 100))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_opportunities_step_funnel_insert` BEFORE INSERT ON `crm_opportunities` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_sales_funnel_steps s WHERE s.id=NEW.current_step_id AND s.hq_id=NEW.hq_id AND s.funnel_id=NEW.funnel_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Current step must belong to the opportunity funnel'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_opportunities_step_funnel_update` BEFORE UPDATE ON `crm_opportunities` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_sales_funnel_steps s WHERE s.id=NEW.current_step_id AND s.hq_id=NEW.hq_id AND s.funnel_id=NEW.funnel_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Current step must belong to the opportunity funnel'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_opportunities_step_funnel_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_opportunities_step_funnel_update`');
        DB::statement('ALTER TABLE `crm_opportunities` DROP CHECK `crm_opportunities_probability_check`');
    }
};
