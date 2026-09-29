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

        DB::statement('ALTER TABLE `consignment_number_ranges` ADD CONSTRAINT `number_ranges_final_check` CHECK ((regexp_like(`first_number`,_utf8mb4\'^[0-9]{6,28}$\') and regexp_like(`last_number`,_utf8mb4\'^[0-9]{6,28}$\') and (char_length(`first_number`) = `total_length`) and (char_length(`last_number`) = `total_length`)))');
        DB::statement('ALTER TABLE `consignment_number_ranges` ADD CONSTRAINT `number_ranges_prefix_check` CHECK ((regexp_like(`numeric_prefix`,_utf8mb4\'^[0-9]+$\') and (char_length(`numeric_prefix`) < `total_length`)))');
        DB::statement('ALTER TABLE `consignment_number_ranges` ADD CONSTRAINT `number_ranges_serial_check` CHECK ((regexp_like(`serial_start`,_utf8mb4\'^[0-9]+$\') and regexp_like(`serial_end`,_utf8mb4\'^[0-9]+$\') and ((`next_serial` is null) or regexp_like(`next_serial`,_utf8mb4\'^[0-9]+$\')) and (char_length(`serial_start`) = `serial_width`) and (char_length(`serial_end`) = `serial_width`) and ((`next_serial` is null) or (char_length(`next_serial`) = `serial_width`))))');
        DB::statement('ALTER TABLE `consignment_number_ranges` ADD CONSTRAINT `number_ranges_total_length_check` CHECK ((`total_length` between 6 and 28))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `consignment_number_ranges` DROP CHECK `number_ranges_final_check`');
        DB::statement('ALTER TABLE `consignment_number_ranges` DROP CHECK `number_ranges_prefix_check`');
        DB::statement('ALTER TABLE `consignment_number_ranges` DROP CHECK `number_ranges_serial_check`');
        DB::statement('ALTER TABLE `consignment_number_ranges` DROP CHECK `number_ranges_total_length_check`');
    }
};
