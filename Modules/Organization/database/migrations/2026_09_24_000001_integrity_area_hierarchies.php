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

        DB::statement('ALTER TABLE `area_hierarchies` ADD CONSTRAINT `area_hierarchy_not_self` CHECK ((`parent_area_id` <> `child_area_id`))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `area_hierarchies` DROP CHECK `area_hierarchy_not_self`');
    }
};
