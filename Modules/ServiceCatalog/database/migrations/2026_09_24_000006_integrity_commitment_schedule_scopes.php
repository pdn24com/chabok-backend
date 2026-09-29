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
CREATE TRIGGER `commitment_scope_pub_ins` BEFORE INSERT ON `commitment_schedule_scopes` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM commitment_schedule_versions v WHERE v.id = NEW.commitment_schedule_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule child'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `commitment_scope_pub_upd` BEFORE UPDATE ON `commitment_schedule_scopes` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM commitment_schedule_versions v WHERE v.id = OLD.commitment_schedule_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule child'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `commitment_scope_pub_del` BEFORE DELETE ON `commitment_schedule_scopes` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM commitment_schedule_versions v WHERE v.id = OLD.commitment_schedule_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule child'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `commitment_scope_pub_ins`');
        DB::unprepared('DROP TRIGGER IF EXISTS `commitment_scope_pub_upd`');
        DB::unprepared('DROP TRIGGER IF EXISTS `commitment_scope_pub_del`');
    }
};
