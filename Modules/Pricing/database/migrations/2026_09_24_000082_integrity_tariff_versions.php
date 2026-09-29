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
CREATE TRIGGER `tariff_versions_published_update` BEFORE UPDATE ON `tariff_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (((OLD.status = 'PUBLISHED' AND NEW.status = 'SUPERSEDED') OR (OLD.status = 'SUPERSEDED' AND NEW.status = 'ARCHIVED')) AND (NEW.tariff_family_id <=> OLD.tariff_family_id AND NEW.zone_set_version_id <=> OLD.zone_set_version_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.volumetric_divisor <=> OLD.volumetric_divisor AND NEW.weight_rounding_step_kg <=> OLD.weight_rounding_step_kg AND NEW.rounding_mode <=> OLD.rounding_mode AND NEW.content_digest <=> OLD.content_digest)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing version'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `tariff_v2_published_update` BEFORE UPDATE ON `tariff_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (NEW.zone_policy <=> OLD.zone_policy AND NEW.freight_matrices <=> OLD.freight_matrices) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing V2 content'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `tariff_service_content_update` BEFORE UPDATE ON `tariff_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (NEW.matrix_basis <=> OLD.matrix_basis AND NEW.is_default <=> OLD.is_default) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing service content'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `tariff_versions_published_delete` BEFORE DELETE ON `tariff_versions` FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing version'; END IF; END
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_versions_published_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_v2_published_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_service_content_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `tariff_versions_published_delete`');
    }
};
