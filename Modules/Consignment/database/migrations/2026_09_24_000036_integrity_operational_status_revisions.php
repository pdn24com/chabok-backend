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
CREATE TRIGGER `status_revisions_update` BEFORE UPDATE ON `operational_status_revisions` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable status revision'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `status_revisions_delete` BEFORE DELETE ON `operational_status_revisions` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable status revision'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `status_revisions_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `status_revisions_delete`');
    }
};
