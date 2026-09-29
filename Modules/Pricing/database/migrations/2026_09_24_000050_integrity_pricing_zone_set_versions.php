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
CREATE TRIGGER `pricing_zone_set_versions_published_update` BEFORE UPDATE ON `pricing_zone_set_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (((OLD.status = 'PUBLISHED' AND NEW.status = 'SUPERSEDED') OR (OLD.status = 'SUPERSEDED' AND NEW.status = 'ARCHIVED')) AND (NEW.pricing_zone_set_id <=> OLD.pricing_zone_set_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.content_digest <=> OLD.content_digest)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing version'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `pricing_zone_set_versions_published_delete` BEFORE DELETE ON `pricing_zone_set_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing version'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `pricing_zone_set_versions_published_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `pricing_zone_set_versions_published_delete`');
    }
};
