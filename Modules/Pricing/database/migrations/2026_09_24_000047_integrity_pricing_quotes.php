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
CREATE TRIGGER `pricing_quotes_immutable_update` BEFORE UPDATE ON `pricing_quotes` FOR EACH ROW BEGIN IF NOT (OLD.status = 'OFFERED' AND NEW.status IN ('ACCEPTED','EXPIRED','SUPERSEDED','REJECTED','VOID') AND (NEW.hq_id <=> OLD.hq_id AND NEW.requested_by <=> OLD.requested_by AND NEW.purpose <=> OLD.purpose AND NEW.tariff_version_id <=> OLD.tariff_version_id AND NEW.zone_set_version_id <=> OLD.zone_set_version_id AND NEW.service_offering_id <=> OLD.service_offering_id AND NEW.service_offering_version_id <=> OLD.service_offering_version_id AND NEW.origin_zone_id <=> OLD.origin_zone_id AND NEW.destination_zone_id <=> OLD.destination_zone_id AND NEW.currency <=> OLD.currency AND NEW.subtotal_amount <=> OLD.subtotal_amount AND NEW.discount_amount <=> OLD.discount_amount AND NEW.tax_amount <=> OLD.tax_amount AND NEW.total_amount <=> OLD.total_amount AND NEW.normalized_input <=> OLD.normalized_input AND NEW.resolution_evidence <=> OLD.resolution_evidence AND NEW.warnings <=> OLD.warnings AND NEW.input_fingerprint <=> OLD.input_fingerprint AND NEW.result_fingerprint <=> OLD.result_fingerprint AND NEW.idempotency_key <=> OLD.idempotency_key AND NEW.calculated_at <=> OLD.calculated_at AND NEW.expires_at <=> OLD.expires_at)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing Quote'; END IF; END
SQL
        );
        DB::unprepared(<<<'SQL'
CREATE TRIGGER `pricing_quotes_immutable_delete` BEFORE DELETE ON `pricing_quotes` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing Quote'
SQL
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS `pricing_quotes_immutable_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `pricing_quotes_immutable_delete`');
    }
};
