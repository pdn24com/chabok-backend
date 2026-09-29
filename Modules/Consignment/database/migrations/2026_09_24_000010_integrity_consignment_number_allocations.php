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

        DB::statement('ALTER TABLE `consignment_number_allocations` ADD CONSTRAINT `number_allocations_numeric_check` CHECK (regexp_like(`consignment_number`,_utf8mb4\'^[0-9]{6,28}$\'))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `consignment_number_allocations_immutable_update` BEFORE UPDATE ON `consignment_number_allocations` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment number allocation'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `consignment_number_allocations_immutable_delete` BEFORE DELETE ON `consignment_number_allocations` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment number allocation'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `consignment_number_allocations` DROP CHECK `number_allocations_numeric_check`');
        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_number_allocations_immutable_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_number_allocations_immutable_delete`');
    }
};
