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

        DB::statement('ALTER TABLE `operational_statuses` ADD CONSTRAINT `status_code_check` CHECK ((regexp_like(`code`,_utf8mb4\'^[A-Z][A-Z0-9_]{1,31}$\') and (`code` <> _utf8mb4\'D01\')))');
        DB::statement('ALTER TABLE `operational_statuses` ADD CONSTRAINT `status_owner_check` CHECK ((`owner_key` = coalesce(`hq_id`,_utf8mb4\'GLOBAL\')))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `operational_status_identity` BEFORE UPDATE ON `operational_statuses` FOR EACH ROW BEGIN IF NOT (OLD.code <=> NEW.code) OR NOT (OLD.hq_id <=> NEW.hq_id) OR OLD.owner_key <> NEW.owner_key OR OLD.is_system <> NEW.is_system OR OLD.manifest_enabled <> NEW.manifest_enabled THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable operational status identity'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `operational_status_no_delete` BEFORE DELETE ON `operational_statuses` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Deactivate operational status instead of deleting'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `operational_statuses` DROP CHECK `status_code_check`');
        DB::statement('ALTER TABLE `operational_statuses` DROP CHECK `status_owner_check`');
        DB::unprepared('DROP TRIGGER IF EXISTS `operational_status_identity`');
        DB::unprepared('DROP TRIGGER IF EXISTS `operational_status_no_delete`');
    }
};
