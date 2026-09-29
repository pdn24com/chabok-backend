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

        // MySQL refuses a CHECK that reads an auto-increment column (error 3818), so the self-reference rule is a trigger.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_financial_entries_reverses_self_insert` BEFORE INSERT ON `crm_financial_entries` FOR EACH ROW BEGIN IF NEW.reverses_id IS NOT NULL AND NEW.reverses_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A row cannot reference itself through reverses_id'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_financial_entries_reverses_self_update` BEFORE UPDATE ON `crm_financial_entries` FOR EACH ROW BEGIN IF NEW.reverses_id IS NOT NULL AND NEW.reverses_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A row cannot reference itself through reverses_id'; END IF; END
SQL
        );
        // The invoice a manual entry cites must belong to the same customer.
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_financial_entries_invoice_customer_insert` BEFORE INSERT ON `crm_financial_entries` FOR EACH ROW BEGIN IF NEW.invoice_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_external_invoices i WHERE i.id=NEW.invoice_id AND i.hq_id=NEW.hq_id AND i.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Financial entry invoice must belong to the same customer'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_financial_entries_invoice_customer_update` BEFORE UPDATE ON `crm_financial_entries` FOR EACH ROW BEGIN IF NEW.invoice_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_external_invoices i WHERE i.id=NEW.invoice_id AND i.hq_id=NEW.hq_id AND i.customer_id=NEW.customer_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Financial entry invoice must belong to the same customer'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_financial_entries_invoice_customer_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_financial_entries_invoice_customer_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_financial_entries_reverses_self_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_financial_entries_reverses_self_insert`');
    }
};
