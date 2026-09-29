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

        DB::statement('ALTER TABLE `user_role_assignments` ADD CONSTRAINT `assignments_platform_shape` CHECK ((((`scope_type` = _utf8mb4\'PLATFORM\') and (`hq_id` is null) and (`scope_id` is null) and (`includes_descendants` = 0)) or ((`scope_type` <> _utf8mb4\'PLATFORM\') and (`hq_id` is not null))))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `user_role_assignments` DROP CHECK `assignments_platform_shape`');
    }
};
