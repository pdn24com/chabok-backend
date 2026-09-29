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
CREATE TRIGGER `operational_exception_history_immutable_update` BEFORE UPDATE ON `operational_exception_history` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable operational history'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `operational_exception_history_immutable_delete` BEFORE DELETE ON `operational_exception_history` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable operational history'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `operational_exception_history_immutable_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `operational_exception_history_immutable_delete`');
    }
};
