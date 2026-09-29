<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
// Cycles deeper than the direct self-reference stay a write-path check; MySQL cannot walk the tree here.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_customer_departments` ADD CONSTRAINT `crm_customer_departments_parent_self_check` CHECK (((`parent_department_id` is null) or (`parent_department_id` <> `id`)))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_customer_departments_company_kind_insert` BEFORE INSERT ON `crm_customer_departments` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.company_customer_id AND c.hq_id=NEW.hq_id AND c.kind='COMPANY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A customer department belongs to a COMPANY record only'; END IF; IF NEW.parent_department_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customer_departments d WHERE d.id=NEW.parent_department_id AND d.hq_id=NEW.hq_id AND d.company_customer_id=NEW.company_customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Parent department must belong to the same company'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_customer_departments_company_kind_update` BEFORE UPDATE ON `crm_customer_departments` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_customers c WHERE c.id=NEW.company_customer_id AND c.hq_id=NEW.hq_id AND c.kind='COMPANY') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A customer department belongs to a COMPANY record only'; END IF; IF NEW.parent_department_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_customer_departments d WHERE d.id=NEW.parent_department_id AND d.hq_id=NEW.hq_id AND d.company_customer_id=NEW.company_customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Parent department must belong to the same company'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_customer_departments_company_kind_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_customer_departments_company_kind_insert`');
        DB::statement('ALTER TABLE `crm_customer_departments` DROP CHECK `crm_customer_departments_parent_self_check`');
    }
};
