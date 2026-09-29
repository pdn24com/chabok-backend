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

        DB::statement('ALTER TABLE `crm_contracts` ADD CONSTRAINT `crm_contracts_validity_window_check` CHECK (((`start_date` is null) or (`end_date` is null) or (`end_date` >= `start_date`)))');
        // The proforma the contract was built on must belong to the same customer as the contract itself.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_contracts_proforma_customer_insert` BEFORE INSERT ON `crm_contracts` FOR EACH ROW BEGIN IF NEW.proforma_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_document_versions v JOIN crm_sales_documents d ON d.id=v.document_id AND d.hq_id=v.hq_id WHERE v.id=NEW.proforma_version_id AND v.hq_id=NEW.hq_id AND d.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proforma version must belong to the contract customer'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_contracts_proforma_customer_update` BEFORE UPDATE ON `crm_contracts` FOR EACH ROW BEGIN IF NEW.proforma_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_document_versions v JOIN crm_sales_documents d ON d.id=v.document_id AND d.hq_id=v.hq_id WHERE v.id=NEW.proforma_version_id AND v.hq_id=NEW.hq_id AND d.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Proforma version must belong to the contract customer'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_contracts_proforma_customer_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_contracts_proforma_customer_insert`');
        DB::statement('ALTER TABLE `crm_contracts` DROP CHECK `crm_contracts_validity_window_check`');
    }
};
