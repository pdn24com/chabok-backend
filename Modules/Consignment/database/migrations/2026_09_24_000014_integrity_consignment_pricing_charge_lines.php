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
CREATE TRIGGER `consignment_pricing_charge_lines_immutable_update` BEFORE UPDATE ON `consignment_pricing_charge_lines` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `consignment_pricing_charge_lines_immutable_delete` BEFORE DELETE ON `consignment_pricing_charge_lines` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_pricing_charge_lines_immutable_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `consignment_pricing_charge_lines_immutable_delete`');
    }
};
