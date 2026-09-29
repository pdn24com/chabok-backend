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
CREATE TRIGGER `crm_sales_document_versions_previous_self_insert` BEFORE INSERT ON `crm_sales_document_versions` FOR EACH ROW BEGIN IF NEW.previous_version_id IS NOT NULL AND NEW.previous_version_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A row cannot reference itself through previous_version_id'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_sales_document_versions_previous_self_update` BEFORE UPDATE ON `crm_sales_document_versions` FOR EACH ROW BEGIN IF NEW.previous_version_id IS NOT NULL AND NEW.previous_version_id = NEW.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A row cannot reference itself through previous_version_id'; END IF; END
SQL
        );
        DB::statement('ALTER TABLE `crm_sales_document_versions` ADD CONSTRAINT `crm_sales_document_versions_version_no_check` CHECK ((`version_no` >= 1))');
        DB::statement('ALTER TABLE `crm_sales_document_versions` ADD CONSTRAINT `crm_sales_document_versions_total_check` CHECK ((`total` >= 0))');
        // An issued version carries frozen content, so its snapshot, expiry and hash can no longer be missing.
        DB::statement('ALTER TABLE `crm_sales_document_versions` ADD CONSTRAINT `crm_sales_document_versions_issued_content_check` CHECK (((`status` in (_utf8mb4\'DRAFT\', _utf8mb4\'REVIEW\')) or ((`customer_snapshot` is not null) and (`expires_at` is not null) and (`content_hash` is not null))))');
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_sales_document_versions_previous_document_insert` BEFORE INSERT ON `crm_sales_document_versions` FOR EACH ROW BEGIN IF NEW.previous_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_document_versions v WHERE v.id=NEW.previous_version_id AND v.hq_id=NEW.hq_id AND v.document_id=NEW.document_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Previous version must belong to the same sales document'; END IF;  END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `crm_sales_document_versions_previous_document_update` BEFORE UPDATE ON `crm_sales_document_versions` FOR EACH ROW BEGIN IF NEW.previous_version_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM crm_sales_document_versions v WHERE v.id=NEW.previous_version_id AND v.hq_id=NEW.hq_id AND v.document_id=NEW.document_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Previous version must belong to the same sales document'; END IF;  END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_document_versions_previous_document_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_document_versions_previous_document_insert`');
        DB::statement('ALTER TABLE `crm_sales_document_versions` DROP CHECK `crm_sales_document_versions_issued_content_check`');
        DB::statement('ALTER TABLE `crm_sales_document_versions` DROP CHECK `crm_sales_document_versions_total_check`');
        DB::statement('ALTER TABLE `crm_sales_document_versions` DROP CHECK `crm_sales_document_versions_version_no_check`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_document_versions_previous_self_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `crm_sales_document_versions_previous_self_insert`');
    }
};
