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

        DB::statement('ALTER TABLE `crm_catalog_categories` ADD CONSTRAINT `crm_catalog_categories_parent_self_check` CHECK (((`parent_id` is null) or (`parent_id` <> `id`)))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_catalog_categories` DROP CHECK `crm_catalog_categories_parent_self_check`');
    }
};
