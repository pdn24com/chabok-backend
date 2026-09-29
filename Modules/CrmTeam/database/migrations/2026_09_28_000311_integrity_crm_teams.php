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

        // MySQL refuses a CHECK that reads an auto-increment column (error 3818), so the self-reference rule is a trigger.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_teams_parent_self_insert` BEFORE INSERT ON `crm_teams` FOR EACH ROW BEGIN IF NEW.parent_team_id IS NOT NULL AND NEW.parent_team_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A row cannot reference itself through parent_team_id'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_teams_parent_self_update` BEFORE UPDATE ON `crm_teams` FOR EACH ROW BEGIN IF NEW.parent_team_id IS NOT NULL AND NEW.parent_team_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A row cannot reference itself through parent_team_id'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_teams_parent_self_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_teams_parent_self_insert`');
    }
};
