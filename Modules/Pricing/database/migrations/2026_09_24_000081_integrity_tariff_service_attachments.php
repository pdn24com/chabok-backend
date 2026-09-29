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
CREATE TRIGGER `tariff_attachment_insert` BEFORE INSERT ON `tariff_service_attachments` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions WHERE id = NEW.tariff_version_id AND status <> 'DRAFT') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing service attachment'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `tariff_attachment_update` BEFORE UPDATE ON `tariff_service_attachments` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions WHERE id = OLD.tariff_version_id AND status <> 'DRAFT') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing service attachment'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `tariff_attachment_delete` BEFORE DELETE ON `tariff_service_attachments` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions WHERE id = OLD.tariff_version_id AND status <> 'DRAFT') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing service attachment'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_attachment_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_attachment_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_attachment_delete`');
    }
};
