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

        DB::statement('ALTER TABLE `crm_customer_address` ADD CONSTRAINT `crm_customer_address_latitude_check` CHECK (((`latitude` is null) or ((`latitude` >= -(90)) and (`latitude` <= 90))))');
        DB::statement('ALTER TABLE `crm_customer_address` ADD CONSTRAINT `crm_customer_address_location_pair_check` CHECK ((((`latitude` is null) and (`longitude` is null)) or ((`latitude` is not null) and (`longitude` is not null))))');
        DB::statement('ALTER TABLE `crm_customer_address` ADD CONSTRAINT `crm_customer_address_longitude_check` CHECK (((`longitude` is null) or ((`longitude` >= -(180)) and (`longitude` <= 180))))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_customer_address` DROP CHECK `crm_customer_address_latitude_check`');
        DB::statement('ALTER TABLE `crm_customer_address` DROP CHECK `crm_customer_address_location_pair_check`');
        DB::statement('ALTER TABLE `crm_customer_address` DROP CHECK `crm_customer_address_longitude_check`');
    }
};
