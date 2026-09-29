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
CREATE TRIGGER `crm_sales_documents_current_version_insert` BEFORE INSERT ON `crm_sales_documents` FOR EACH ROW BEGIN IF NEW.current_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_document_versions v WHERE v.id=NEW.current_version_id AND v.hq_id=NEW.hq_id AND v.document_id=NEW.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Current version must belong to the same sales document'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_sales_documents_current_version_update` BEFORE UPDATE ON `crm_sales_documents` FOR EACH ROW BEGIN IF NEW.current_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_document_versions v WHERE v.id=NEW.current_version_id AND v.hq_id=NEW.hq_id AND v.document_id=NEW.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Current version must belong to the same sales document'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_documents_current_version_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_documents_current_version_insert`');
    }
};
