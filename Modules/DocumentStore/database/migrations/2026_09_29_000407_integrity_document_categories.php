<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
// Deeper cycles than the direct self-reference stay a write-path check; MySQL cannot walk the tree in a constraint.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `document_categories` ADD CONSTRAINT `document_categories_parent_self_check` CHECK (((`parent_id` is null) or (`parent_id` <> `id`)))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `document_categories` DROP CHECK `document_categories_parent_self_check`');
    }
};
