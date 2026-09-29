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

        // MySQL cannot express a partial unique index, so "at most one primary industry per customer"
        // is enforced through a stored generated column that is NULL for every non-primary row.
        DB::statement('ALTER TABLE `crm_customer_industry` ADD COLUMN `primary_industry_customer_id` int unsigned GENERATED ALWAYS AS (if((`is_primary` = 1), `customer_id`, NULL)) STORED');
        DB::statement('ALTER TABLE `crm_customer_industry` ADD UNIQUE INDEX `crm_customer_industry_primary_unique` (`hq_id`, `primary_industry_customer_id`)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_customer_industry` DROP INDEX `crm_customer_industry_primary_unique`');
        DB::statement('ALTER TABLE `crm_customer_industry` DROP COLUMN `primary_industry_customer_id`');
    }
};
