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

        // MySQL cannot express a partial unique index, so "one primary active account per customer"
        // is enforced through a stored generated column that is NULL for every other row.
        DB::statement('ALTER TABLE `crm_bank_accounts` ADD COLUMN `primary_account_customer_id` int unsigned GENERATED ALWAYS AS (if(((`is_primary` = 1) and (`status` = _utf8mb4\'ACTIVE\')), `customer_id`, NULL)) STORED');
        DB::statement('ALTER TABLE `crm_bank_accounts` ADD UNIQUE INDEX `crm_bank_accounts_primary_account_unique` (`hq_id`, `primary_account_customer_id`)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_bank_accounts` DROP INDEX `crm_bank_accounts_primary_account_unique`');
        DB::statement('ALTER TABLE `crm_bank_accounts` DROP COLUMN `primary_account_customer_id`');
    }
};
