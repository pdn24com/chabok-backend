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
CREATE TRIGGER `pr_zone_pub_ins` BEFORE INSERT ON `pricing_zones` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zone_set_versions v WHERE v.id = NEW.zone_set_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `pr_zone_pub_upd` BEFORE UPDATE ON `pricing_zones` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zone_set_versions v WHERE v.id = OLD.zone_set_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `pr_zone_pub_del` BEFORE DELETE ON `pricing_zones` FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zone_set_versions v WHERE v.id = OLD.zone_set_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `pr_zone_pub_ins`');
        DB::unprepared('DROP TRIGGER IF EXISTS `pr_zone_pub_upd`');
        DB::unprepared('DROP TRIGGER IF EXISTS `pr_zone_pub_del`');
    }
};
