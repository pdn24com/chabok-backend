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

        DB::statement('ALTER TABLE `roles` ADD CONSTRAINT `roles_owner_shape` CHECK ((((`role_kind` = _utf8mb4\'CUSTOM\') and (`hq_id` is not null) and (`owner_key` = `hq_id`)) or ((`role_kind` <> _utf8mb4\'CUSTOM\') and (`hq_id` is null) and (`owner_key` = _utf8mb4\'GLOBAL\'))))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `roles` DROP CHECK `roles_owner_shape`');
    }
};
