<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PURPOSES = ['SALES', 'PURCHASE', 'COMMISSION', 'INTERNAL_TRANSFER'];
    private const VERSION_STATUSES = ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED', 'CANCELLED'];

    public function up(): void
    {
        Schema::create('pricing_charge_types', function (Blueprint $table): void {
            $table->char('charge_type_id', 36)->primary();
            $table->string('code', 80)->unique();
            $table->enum('category', ['BASE', 'SURCHARGE', 'DISCOUNT', 'TAX', 'COMMISSION']);
            $table->string('accounting_mapping_key', 120);
            $table->boolean('taxable')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps(6);
        });
        Schema::create('pricing_zone_sets', function (Blueprint $table): void {
            $table->char('pricing_zone_set_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->string('owner_key', 36);
            $table->string('code', 80);
            $table->enum('purpose', self::PURPOSES);
            $table->string('title', 200);
            $table->char('created_by', 36);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['owner_key', 'purpose', 'code'], 'pricing_zone_set_owner_code_unique');
        });
        Schema::create('pricing_zone_set_versions', function (Blueprint $table): void {
            $table->char('zone_set_version_id', 36)->primary();
            $table->char('pricing_zone_set_id', 36);
            $table->char('hq_id', 36)->nullable();
            $table->unsignedInteger('version_number');
            $table->char('previous_version_id', 36)->nullable();
            $table->enum('status', self::VERSION_STATUSES)->default('DRAFT');
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_to', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('content_digest', 64)->nullable();
            $table->char('created_by', 36);
            $table->char('approved_by', 36)->nullable();
            $table->char('published_by', 36)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('pricing_zone_set_id')->references('pricing_zone_set_id')->on('pricing_zone_sets')->restrictOnDelete();
            $table->foreign('previous_version_id')->references('zone_set_version_id')->on('pricing_zone_set_versions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['pricing_zone_set_id', 'version_number'], 'pricing_zone_set_version_number_unique');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'pricing_zone_set_effective_index');
        });
        Schema::create('pricing_zones', function (Blueprint $table): void {
            $table->char('pricing_zone_id', 36)->primary();
            $table->char('zone_set_version_id', 36);
            $table->string('code', 80);
            $table->string('title', 200);
            $table->boolean('remote_area')->default(false);
            $table->foreign('zone_set_version_id')->references('zone_set_version_id')->on('pricing_zone_set_versions')->cascadeOnDelete();
            $table->unique(['zone_set_version_id', 'code'], 'pricing_zone_code_unique');
        });
        Schema::create('pricing_zone_members', function (Blueprint $table): void {
            $table->char('zone_member_id', 36)->primary();
            $table->char('pricing_zone_id', 36);
            $table->enum('member_type', ['EXPLICIT_OVERRIDE', 'POSTAL_RANGE', 'CITY']);
            $table->string('reference_value', 200);
            $table->string('range_end', 200)->nullable();
            $table->unsignedSmallInteger('precedence');
            $table->foreign('pricing_zone_id')->references('pricing_zone_id')->on('pricing_zones')->cascadeOnDelete();
            $table->index(['member_type', 'reference_value', 'range_end', 'precedence'], 'pricing_zone_member_resolution_index');
        });
        Schema::create('tariff_families', function (Blueprint $table): void {
            $table->char('tariff_family_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->string('owner_key', 36);
            $table->string('code', 80);
            $table->enum('purpose', self::PURPOSES);
            $table->enum('scope_type', ['PLATFORM', 'TENANT', 'SEGMENT', 'CUSTOMER', 'CONTRACT'])->default('TENANT');
            $table->string('scope_value', 120)->nullable();
            $table->char('currency', 3)->default('IRR');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->char('created_by', 36);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['owner_key', 'purpose', 'code'], 'tariff_family_owner_code_unique');
            $table->index(['hq_id', 'purpose', 'scope_type', 'scope_value', 'priority'], 'tariff_family_resolution_index');
        });
        Schema::create('tariff_versions', function (Blueprint $table): void {
            $table->char('tariff_version_id', 36)->primary();
            $table->char('tariff_family_id', 36);
            $table->char('hq_id', 36)->nullable();
            $table->char('zone_set_version_id', 36);
            $table->unsignedInteger('version_number');
            $table->char('previous_version_id', 36)->nullable();
            $table->enum('status', self::VERSION_STATUSES)->default('DRAFT');
            $table->timestamp('valid_from', 6)->nullable();
            $table->timestamp('valid_to', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->decimal('volumetric_divisor', 12, 3)->default(5000);
            $table->decimal('weight_rounding_step_kg', 8, 3)->default(0.5);
            $table->enum('rounding_mode', ['HALF_UP', 'HALF_EVEN', 'CEILING', 'FLOOR', 'STEP_UP'])->default('STEP_UP');
            $table->char('content_digest', 64)->nullable();
            $table->char('created_by', 36);
            $table->char('approved_by', 36)->nullable();
            $table->char('published_by', 36)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('published_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('tariff_family_id')->references('tariff_family_id')->on('tariff_families')->restrictOnDelete();
            $table->foreign('zone_set_version_id')->references('zone_set_version_id')->on('pricing_zone_set_versions')->restrictOnDelete();
            $table->foreign('previous_version_id')->references('tariff_version_id')->on('tariff_versions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['tariff_family_id', 'version_number'], 'tariff_version_number_unique');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'tariff_version_effective_index');
        });
        Schema::create('tariff_rate_rules', function (Blueprint $table): void {
            $table->char('rate_rule_id', 36)->primary();
            $table->char('tariff_version_id', 36);
            $table->char('service_offering_version_id', 36);
            $table->char('charge_type_id', 36);
            $table->char('origin_zone_id', 36)->nullable();
            $table->char('destination_zone_id', 36)->nullable();
            $table->enum('calculation_method', ['FIXED', 'PER_UNIT', 'SLAB', 'TIERED', 'PERCENT', 'MIN_MAX']);
            $table->string('basis', 80)->default('BILLABLE_WEIGHT');
            $table->decimal('range_from', 16, 4)->nullable();
            $table->decimal('range_to', 16, 4)->nullable();
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->decimal('unit_rate', 18, 6)->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->unsignedBigInteger('minimum_amount')->nullable();
            $table->unsignedBigInteger('maximum_amount')->nullable();
            $table->json('basis_charge_codes')->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->foreign('tariff_version_id')->references('tariff_version_id')->on('tariff_versions')->cascadeOnDelete();
            $table->foreign('service_offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
            $table->foreign('charge_type_id')->references('charge_type_id')->on('pricing_charge_types')->restrictOnDelete();
            $table->foreign('origin_zone_id')->references('pricing_zone_id')->on('pricing_zones')->restrictOnDelete();
            $table->foreign('destination_zone_id')->references('pricing_zone_id')->on('pricing_zones')->restrictOnDelete();
            $table->index(['tariff_version_id', 'service_offering_version_id', 'origin_zone_id', 'destination_zone_id', 'priority'], 'tariff_rule_lookup_index');
        });
        Schema::create('pricing_quotes', function (Blueprint $table): void {
            $table->char('quote_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('requested_by', 36);
            $table->enum('purpose', self::PURPOSES);
            $table->char('tariff_version_id', 36);
            $table->char('zone_set_version_id', 36);
            $table->char('service_offering_id', 36);
            $table->char('service_offering_version_id', 36);
            $table->char('origin_zone_id', 36);
            $table->char('destination_zone_id', 36);
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total_amount');
            $table->json('normalized_input');
            $table->json('resolution_evidence');
            $table->json('warnings');
            $table->char('input_fingerprint', 64);
            $table->char('result_fingerprint', 64);
            $table->string('idempotency_key', 120);
            $table->enum('status', ['OFFERED', 'ACCEPTED', 'EXPIRED', 'SUPERSEDED', 'REJECTED', 'VOID'])->default('OFFERED');
            $table->timestamp('calculated_at', 6);
            $table->timestamp('expires_at', 6);
            $table->timestamp('accepted_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('requested_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('tariff_version_id')->references('tariff_version_id')->on('tariff_versions')->restrictOnDelete();
            $table->foreign('zone_set_version_id')->references('zone_set_version_id')->on('pricing_zone_set_versions')->restrictOnDelete();
            $table->foreign('service_offering_id')->references('service_offering_id')->on('service_offerings')->restrictOnDelete();
            $table->foreign('service_offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
            $table->foreign('origin_zone_id')->references('pricing_zone_id')->on('pricing_zones')->restrictOnDelete();
            $table->foreign('destination_zone_id')->references('pricing_zone_id')->on('pricing_zones')->restrictOnDelete();
            $table->unique(['hq_id', 'requested_by', 'idempotency_key'], 'pricing_quote_idempotency_unique');
            $table->index(['hq_id', 'status', 'expires_at'], 'pricing_quote_status_index');
        });
        $this->createLineTable('pricing_quote_lines', 'quote_line_id', 'quote_id', 'pricing_quotes');
        Schema::create('pricing_snapshots', function (Blueprint $table): void {
            $table->char('pricing_snapshot_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('quote_id', 36);
            $table->string('object_type', 80);
            $table->char('object_id', 36);
            $table->enum('purpose', self::PURPOSES);
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('total_amount');
            $table->char('input_fingerprint', 64);
            $table->char('result_fingerprint', 64);
            $table->string('acceptance_idempotency_key', 120);
            $table->char('accepted_by', 36);
            $table->timestamp('accepted_at', 6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('quote_id')->references('quote_id')->on('pricing_quotes')->restrictOnDelete();
            $table->foreign('accepted_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'quote_id'], 'pricing_snapshot_quote_unique');
            $table->unique(['hq_id', 'accepted_by', 'acceptance_idempotency_key'], 'pricing_snapshot_idempotency_unique');
            $table->index(['hq_id', 'object_type', 'object_id', 'accepted_at'], 'pricing_snapshot_object_index');
        });
        $this->createLineTable('pricing_charge_lines', 'charge_line_id', 'pricing_snapshot_id', 'pricing_snapshots');

        foreach (['pricing_zone_set_versions', 'tariff_versions'] as $table) {
            $contentEqual = $table === 'tariff_versions'
                ? '(NEW.tariff_family_id <=> OLD.tariff_family_id AND NEW.zone_set_version_id <=> OLD.zone_set_version_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.volumetric_divisor <=> OLD.volumetric_divisor AND NEW.weight_rounding_step_kg <=> OLD.weight_rounding_step_kg AND NEW.rounding_mode <=> OLD.rounding_mode AND NEW.content_digest <=> OLD.content_digest)'
                : '(NEW.pricing_zone_set_id <=> OLD.pricing_zone_set_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.content_digest <=> OLD.content_digest)';
            DB::unprepared("CREATE TRIGGER {$table}_published_update BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (((OLD.status = 'PUBLISHED' AND NEW.status = 'SUPERSEDED') OR (OLD.status = 'SUPERSEDED' AND NEW.status = 'ARCHIVED')) AND {$contentEqual}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing version'; END IF; END");
            DB::unprepared("CREATE TRIGGER {$table}_published_delete BEFORE DELETE ON {$table} FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing version'; END IF; END");
        }
        DB::unprepared("CREATE TRIGGER pr_zone_pub_ins BEFORE INSERT ON pricing_zones FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zone_set_versions v WHERE v.zone_set_version_id = NEW.zone_set_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_zone_pub_upd BEFORE UPDATE ON pricing_zones FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zone_set_versions v WHERE v.zone_set_version_id = OLD.zone_set_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_zone_pub_del BEFORE DELETE ON pricing_zones FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zone_set_versions v WHERE v.zone_set_version_id = OLD.zone_set_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_zone_member_pub_ins BEFORE INSERT ON pricing_zone_members FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zones z JOIN pricing_zone_set_versions v ON v.zone_set_version_id = z.zone_set_version_id WHERE z.pricing_zone_id = NEW.pricing_zone_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone member'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_zone_member_pub_upd BEFORE UPDATE ON pricing_zone_members FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zones z JOIN pricing_zone_set_versions v ON v.zone_set_version_id = z.zone_set_version_id WHERE z.pricing_zone_id = OLD.pricing_zone_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone member'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_zone_member_pub_del BEFORE DELETE ON pricing_zone_members FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM pricing_zones z JOIN pricing_zone_set_versions v ON v.zone_set_version_id = z.zone_set_version_id WHERE z.pricing_zone_id = OLD.pricing_zone_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Pricing Zone member'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_rate_rule_pub_ins BEFORE INSERT ON tariff_rate_rules FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions v WHERE v.tariff_version_id = NEW.tariff_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Tariff rule'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_rate_rule_pub_upd BEFORE UPDATE ON tariff_rate_rules FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions v WHERE v.tariff_version_id = OLD.tariff_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Tariff rule'; END IF; END");
        DB::unprepared("CREATE TRIGGER pr_rate_rule_pub_del BEFORE DELETE ON tariff_rate_rules FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM tariff_versions v WHERE v.tariff_version_id = OLD.tariff_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Tariff rule'; END IF; END");
        foreach (['pricing_quote_lines', 'pricing_snapshots', 'pricing_charge_lines'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable accepted Pricing history'");
            DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable accepted Pricing history'");
        }
        $quoteContentEqual = '(NEW.hq_id <=> OLD.hq_id AND NEW.requested_by <=> OLD.requested_by AND NEW.purpose <=> OLD.purpose AND NEW.tariff_version_id <=> OLD.tariff_version_id AND NEW.zone_set_version_id <=> OLD.zone_set_version_id AND NEW.service_offering_id <=> OLD.service_offering_id AND NEW.service_offering_version_id <=> OLD.service_offering_version_id AND NEW.origin_zone_id <=> OLD.origin_zone_id AND NEW.destination_zone_id <=> OLD.destination_zone_id AND NEW.currency <=> OLD.currency AND NEW.subtotal_amount <=> OLD.subtotal_amount AND NEW.discount_amount <=> OLD.discount_amount AND NEW.tax_amount <=> OLD.tax_amount AND NEW.total_amount <=> OLD.total_amount AND NEW.normalized_input <=> OLD.normalized_input AND NEW.resolution_evidence <=> OLD.resolution_evidence AND NEW.warnings <=> OLD.warnings AND NEW.input_fingerprint <=> OLD.input_fingerprint AND NEW.result_fingerprint <=> OLD.result_fingerprint AND NEW.idempotency_key <=> OLD.idempotency_key AND NEW.calculated_at <=> OLD.calculated_at AND NEW.expires_at <=> OLD.expires_at)';
        DB::unprepared("CREATE TRIGGER pricing_quotes_immutable_update BEFORE UPDATE ON pricing_quotes FOR EACH ROW BEGIN IF NOT (OLD.status = 'OFFERED' AND NEW.status IN ('ACCEPTED','EXPIRED','SUPERSEDED','REJECTED','VOID') AND {$quoteContentEqual}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing Quote'; END IF; END");
        DB::unprepared("CREATE TRIGGER pricing_quotes_immutable_delete BEFORE DELETE ON pricing_quotes FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Pricing Quote'");
    }

    private function createLineTable(string $tableName, string $id, string $parentId, string $parentTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($tableName, $id, $parentId, $parentTable): void {
            $table->char($id, 36)->primary();
            $table->char($parentId, 36);
            $table->unsignedSmallInteger('line_number');
            $table->char('charge_type_id', 36);
            $table->char('rate_rule_id', 36);
            $table->string('charge_type_code', 80);
            $table->string('title', 200);
            $table->string('calculation_method', 30);
            $table->string('basis', 80);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_rate', 18, 6)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('accounting_mapping_key', 120);
            $table->json('explanation');
            $table->foreign($parentId)->references($parentId)->on($parentTable)->cascadeOnDelete();
            $table->foreign('charge_type_id')->references('charge_type_id')->on('pricing_charge_types')->restrictOnDelete();
            $table->foreign('rate_rule_id')->references('rate_rule_id')->on('tariff_rate_rules')->restrictOnDelete();
            $table->unique([$parentId, 'line_number'], "{$tableName}_line_number_unique");
        });
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS pricing_quotes_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS pricing_quotes_immutable_delete');
        foreach (['pr_zone_pub_ins', 'pr_zone_pub_upd', 'pr_zone_pub_del', 'pr_zone_member_pub_ins', 'pr_zone_member_pub_upd', 'pr_zone_member_pub_del', 'pr_rate_rule_pub_ins', 'pr_rate_rule_pub_upd', 'pr_rate_rule_pub_del'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
        foreach (['pricing_zone_set_versions', 'tariff_versions'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_published_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_published_delete");
        }
        foreach (['pricing_quote_lines', 'pricing_snapshots', 'pricing_charge_lines'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }
        foreach (['pricing_charge_lines', 'pricing_snapshots', 'pricing_quote_lines', 'pricing_quotes', 'tariff_rate_rules', 'tariff_versions', 'tariff_families', 'pricing_zone_members', 'pricing_zones', 'pricing_zone_set_versions', 'pricing_zone_sets', 'pricing_charge_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
