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
CREATE TRIGGER `commitment_schedule_versions_published_update` BEFORE UPDATE ON `commitment_schedule_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (((OLD.status = 'PUBLISHED' AND NEW.status = 'SUPERSEDED') OR (OLD.status = 'SUPERSEDED' AND NEW.status = 'ARCHIVED')) AND (NEW.commitment_schedule_id <=> OLD.commitment_schedule_id AND NEW.hq_id <=> OLD.hq_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.timezone <=> OLD.timezone AND NEW.calendar_code <=> OLD.calendar_code AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.content_digest <=> OLD.content_digest)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule version'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `commitment_policy_immutable` BEFORE UPDATE ON `commitment_schedule_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (NEW.commitment_policy <=> OLD.commitment_policy) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment policy'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `commitment_schedule_versions_published_delete` BEFORE DELETE ON `commitment_schedule_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule version'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `commitment_schedule_versions_published_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `commitment_policy_immutable`');
        DB::unprepared('DROP TRIGGER IF EXISTS `commitment_schedule_versions_published_delete`');
    }
};
