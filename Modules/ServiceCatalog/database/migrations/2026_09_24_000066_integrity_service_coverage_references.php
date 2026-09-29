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
CREATE TRIGGER `svc_coverage_ref_pub_ins` BEFORE INSERT ON `service_coverage_references` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM service_offering_versions v WHERE v.id = NEW.service_offering_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering child'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `svc_coverage_ref_pub_upd` BEFORE UPDATE ON `service_coverage_references` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM service_offering_versions v WHERE v.id = OLD.service_offering_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering child'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `svc_coverage_ref_pub_del` BEFORE DELETE ON `service_coverage_references` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM service_offering_versions v WHERE v.id = OLD.service_offering_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering child'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `svc_coverage_ref_pub_ins`');
        DB::unprepared('DROP TRIGGER IF EXISTS `svc_coverage_ref_pub_upd`');
        DB::unprepared('DROP TRIGGER IF EXISTS `svc_coverage_ref_pub_del`');
    }
};
