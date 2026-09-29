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

        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_sales_funnel_catalog_insert` BEFORE INSERT ON `crm_sales_funnel` FOR EACH ROW BEGIN IF NEW.catalog_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_catalog_items i WHERE i.id=NEW.catalog_item_id AND i.hq_id=NEW.hq_id AND i.kind='SERVICE') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sales funnel accepts a SERVICE catalog item only'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_sales_funnel_catalog_update` BEFORE UPDATE ON `crm_sales_funnel` FOR EACH ROW BEGIN IF NEW.catalog_item_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_catalog_items i WHERE i.id=NEW.catalog_item_id AND i.hq_id=NEW.hq_id AND i.kind='SERVICE') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sales funnel accepts a SERVICE catalog item only'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_funnel_catalog_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_funnel_catalog_update`');
    }
};
