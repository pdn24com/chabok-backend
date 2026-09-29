<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Database-only invariants; see output/refactor/database-invariants.md for the required SQL-write guarantees.
// The remaining rule, "allocated total never exceeds the receipt amount", needs the sum of the sibling rows
// and stays a locked SQL write; a row-level constraint cannot express it.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `crm_financial_allocations` ADD CONSTRAINT `crm_financial_allocations_amount_check` CHECK ((`amount` > 0))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_financial_allocations_receipt_kind_insert` BEFORE INSERT ON `crm_financial_allocations` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_financial_entries e JOIN crm_external_invoices i ON i.hq_id=e.hq_id AND i.customer_id=e.customer_id WHERE e.id=NEW.receipt_entry_id AND e.hq_id=NEW.hq_id AND e.kind='RECEIPT' AND i.id=NEW.invoice_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Allocation needs a RECEIPT entry and an invoice of the same customer'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_financial_allocations_receipt_kind_update` BEFORE UPDATE ON `crm_financial_allocations` FOR EACH ROW BEGIN IF NOT EXISTS (SELECT 1 FROM crm_financial_entries e JOIN crm_external_invoices i ON i.hq_id=e.hq_id AND i.customer_id=e.customer_id WHERE e.id=NEW.receipt_entry_id AND e.hq_id=NEW.hq_id AND e.kind='RECEIPT' AND i.id=NEW.invoice_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Allocation needs a RECEIPT entry and an invoice of the same customer'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_financial_allocations_receipt_kind_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_financial_allocations_receipt_kind_insert`');
        DB::statement('ALTER TABLE `crm_financial_allocations` DROP CHECK `crm_financial_allocations_amount_check`');
    }
};
