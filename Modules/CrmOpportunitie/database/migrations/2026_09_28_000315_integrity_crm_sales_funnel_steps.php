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

        DB::statement('ALTER TABLE `crm_sales_funnel_steps` ADD CONSTRAINT `crm_sales_funnel_steps_sort_order_check` CHECK ((`sort_order` > 0))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_sales_funnel_steps` DROP CHECK `crm_sales_funnel_steps_sort_order_check`');
    }
};
