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

        DB::statement('ALTER TABLE `nodes` ADD CONSTRAINT `nodes_country_check` CHECK ((`country_code` = _utf8mb4\'IR\'))');
        DB::statement('ALTER TABLE `nodes` ADD CONSTRAINT `nodes_latitude_check` CHECK (((`latitude` is null) or ((`latitude` >= -(90)) and (`latitude` <= 90))))');
        DB::statement('ALTER TABLE `nodes` ADD CONSTRAINT `nodes_location_pair_check` CHECK ((((`latitude` is null) and (`longitude` is null)) or ((`latitude` is not null) and (`longitude` is not null))))');
        DB::statement('ALTER TABLE `nodes` ADD CONSTRAINT `nodes_longitude_check` CHECK (((`longitude` is null) or ((`longitude` >= -(180)) and (`longitude` <= 180))))');
        DB::statement('ALTER TABLE `nodes` ADD CONSTRAINT `nodes_type_check` CHECK ((`node_type` in (_utf8mb4\'BRANCH\',_utf8mb4\'HUB\',_utf8mb4\'GATEWAY\',_utf8mb4\'AGENT\')))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `nodes` DROP CHECK `nodes_country_check`');
        DB::statement('ALTER TABLE `nodes` DROP CHECK `nodes_latitude_check`');
        DB::statement('ALTER TABLE `nodes` DROP CHECK `nodes_location_pair_check`');
        DB::statement('ALTER TABLE `nodes` DROP CHECK `nodes_longitude_check`');
        DB::statement('ALTER TABLE `nodes` DROP CHECK `nodes_type_check`');
    }
};
