<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// The row id is an auto-increment that MySQL has not assigned yet in a BEFORE INSERT trigger (NEW.id is 0),
// so the fallback code is drawn from UUID() instead. The API always sends area_code; this only covers raw SQL writes.
// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `areas_assign_code_before_insert` BEFORE INSERT ON `areas` FOR EACH ROW BEGIN
    IF NEW.area_code IS NULL OR NEW.area_code = '' THEN
        SET NEW.area_code = CONCAT('AREA-', UPPER(LEFT(REPLACE(UUID(), '-', ''), 12)));
    END IF;
END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `areas_assign_code_before_insert`');
    }
};
