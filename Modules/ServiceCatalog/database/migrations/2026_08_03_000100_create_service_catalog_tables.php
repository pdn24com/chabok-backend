<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VERSION_STATUSES = ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED', 'PUBLISHED', 'SUPERSEDED', 'ARCHIVED', 'CANCELLED'];

    public function up(): void
    {
        foreach (['service_types', 'shipping_methods', 'service_options', 'service_offerings'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
                $singular = rtrim($tableName, 's');
                $table->char("{$singular}_id", 36)->primary();
                $table->char('hq_id', 36)->nullable();
                $table->string('owner_key', 36);
                $table->string('code', 80);
                $table->enum('status', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('ACTIVE');
                $table->char('created_by', 36);
                $table->timestamps(6);
                $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
                $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
                $table->unique(['owner_key', 'code'], "{$tableName}_owner_code_unique");
                $table->index(['hq_id', 'status', 'code'], "{$tableName}_tenant_list_index");
            });
        }

        $this->createVersionTable('service_type_versions', 'service_type_version_id', 'service_type_id', 'service_types');
        $this->createVersionTable('shipping_method_versions', 'shipping_method_version_id', 'shipping_method_id', 'shipping_methods');
        $this->createVersionTable('service_option_versions', 'service_option_version_id', 'service_option_id', 'service_options');

        Schema::create('service_offering_versions', function (Blueprint $table): void {
            $table->char('service_offering_version_id', 36)->primary();
            $table->char('service_offering_id', 36);
            $table->char('hq_id', 36)->nullable();
            $table->unsignedInteger('version_number');
            $table->char('previous_version_id', 36)->nullable();
            $table->char('service_type_version_id', 36);
            $table->char('shipping_method_version_id', 36);
            $table->json('labels');
            $table->text('description')->nullable();
            $table->json('sla_policy');
            $table->json('availability_summary')->nullable();
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
            $table->foreign('service_offering_id')->references('service_offering_id')->on('service_offerings')->restrictOnDelete();
            $table->foreign('previous_version_id')->references('service_offering_version_id')->on('service_offering_versions')->restrictOnDelete();
            $table->foreign('service_type_version_id')->references('service_type_version_id')->on('service_type_versions')->restrictOnDelete();
            $table->foreign('shipping_method_version_id')->references('shipping_method_version_id')->on('shipping_method_versions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['service_offering_id', 'version_number'], 'service_offering_version_number_unique');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'service_offering_effective_index');
        });

        Schema::create('service_offering_option_rules', function (Blueprint $table): void {
            $table->char('offering_option_rule_id', 36)->primary();
            $table->char('service_offering_version_id', 36);
            $table->char('service_option_version_id', 36);
            $table->enum('compatibility', ['ALLOWED', 'REQUIRED', 'FORBIDDEN', 'CONDITIONAL']);
            $table->json('condition')->nullable();
            $table->foreign('service_offering_version_id', 'svc_option_rule_offering_version_fk')->references('service_offering_version_id')->on('service_offering_versions')->cascadeOnDelete();
            $table->foreign('service_option_version_id')->references('service_option_version_id')->on('service_option_versions')->restrictOnDelete();
            $table->unique(['service_offering_version_id', 'service_option_version_id'], 'service_offering_option_unique');
        });
        Schema::create('service_eligibility_rules', function (Blueprint $table): void {
            $table->char('eligibility_rule_id', 36)->primary();
            $table->char('service_offering_version_id', 36);
            $table->enum('dimension', ['GEOGRAPHY', 'PHYSICAL', 'CONTENT', 'VALUE', 'COMMERCIAL', 'OPERATIONAL', 'TEMPORAL', 'OPTION', 'CHANNEL']);
            $table->string('fact_key', 120);
            $table->enum('operator', ['EQ', 'NEQ', 'IN', 'NOT_IN', 'MIN', 'MAX', 'BETWEEN', 'EXISTS', 'NOT_EXISTS']);
            $table->json('expected_value');
            $table->string('reason_code', 120);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->foreign('service_offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->cascadeOnDelete();
            $table->index(['service_offering_version_id', 'priority'], 'service_eligibility_order_index');
        });
        Schema::create('service_coverage_references', function (Blueprint $table): void {
            $table->char('coverage_reference_id', 36)->primary();
            $table->char('service_offering_version_id', 36);
            $table->enum('direction', ['ORIGIN', 'DESTINATION', 'LANE', 'BOTH']);
            $table->enum('reference_type', ['COUNTRY', 'PROVINCE', 'CITY', 'POSTAL_RANGE', 'OPERATIONAL_AREA', 'PRICING_ZONE_SET']);
            $table->string('reference_value', 200);
            $table->string('secondary_reference_value', 200)->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->foreign('service_offering_version_id')->references('service_offering_version_id')->on('service_offering_versions')->cascadeOnDelete();
            $table->index(['service_offering_version_id', 'direction', 'reference_type'], 'service_coverage_resolution_index');
        });
        Schema::create('service_availability_bindings', function (Blueprint $table): void {
            $table->char('availability_binding_id', 36)->primary();
            $table->char('service_offering_version_id', 36);
            $table->enum('scope_type', ['PLATFORM', 'TENANT', 'CUSTOMER_SEGMENT', 'CUSTOMER', 'CONTRACT', 'CHANNEL']);
            $table->string('scope_value', 120)->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreign('service_offering_version_id', 'svc_availability_offering_version_fk')->references('service_offering_version_id')->on('service_offering_versions')->cascadeOnDelete();
            $table->index(['service_offering_version_id', 'scope_type', 'scope_value'], 'service_availability_lookup_index');
        });
        Schema::create('service_legacy_mappings', function (Blueprint $table): void {
            $table->char('legacy_mapping_id', 36)->primary();
            $table->char('hq_id', 36)->nullable();
            $table->string('legacy_system', 80);
            $table->string('legacy_type', 80);
            $table->string('legacy_code', 160);
            $table->string('target_type', 80);
            $table->char('target_identity_id', 36);
            $table->char('target_version_id', 36)->nullable();
            $table->json('source_evidence')->nullable();
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->unique(['legacy_system', 'legacy_type', 'legacy_code', 'hq_id'], 'service_legacy_mapping_unique');
        });

        foreach (['service_type_versions', 'shipping_method_versions', 'service_option_versions', 'service_offering_versions'] as $table) {
            $contentEqual = $table === 'service_offering_versions'
                ? '(NEW.service_offering_id <=> OLD.service_offering_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.service_type_version_id <=> OLD.service_type_version_id AND NEW.shipping_method_version_id <=> OLD.shipping_method_version_id AND NEW.labels <=> OLD.labels AND NEW.description <=> OLD.description AND NEW.sla_policy <=> OLD.sla_policy AND NEW.availability_summary <=> OLD.availability_summary AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.content_digest <=> OLD.content_digest)'
                : '(NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.labels <=> OLD.labels AND NEW.description <=> OLD.description AND NEW.definition <=> OLD.definition AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.content_digest <=> OLD.content_digest)';
            DB::unprepared("CREATE TRIGGER {$table}_published_update BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (((OLD.status = 'PUBLISHED' AND NEW.status = 'SUPERSEDED') OR (OLD.status = 'SUPERSEDED' AND NEW.status = 'ARCHIVED')) AND {$contentEqual}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Catalog version'; END IF; END");
            DB::unprepared("CREATE TRIGGER {$table}_published_delete BEFORE DELETE ON {$table} FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Catalog version'; END IF; END");
        }
        foreach ([
            'service_offering_option_rules' => 'svc_option_rule',
            'service_eligibility_rules' => 'svc_eligibility_rule',
            'service_coverage_references' => 'svc_coverage_ref',
            'service_availability_bindings' => 'svc_availability',
        ] as $table => $triggerPrefix) {
            $publishedParent = "EXISTS (SELECT 1 FROM service_offering_versions v WHERE v.service_offering_version_id = OLD.service_offering_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED'))";
            $publishedNewParent = "EXISTS (SELECT 1 FROM service_offering_versions v WHERE v.service_offering_version_id = NEW.service_offering_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED'))";
            DB::unprepared("CREATE TRIGGER {$triggerPrefix}_pub_ins BEFORE INSERT ON {$table} FOR EACH ROW BEGIN IF {$publishedNewParent} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering child'; END IF; END");
            DB::unprepared("CREATE TRIGGER {$triggerPrefix}_pub_upd BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$publishedParent} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering child'; END IF; END");
            DB::unprepared("CREATE TRIGGER {$triggerPrefix}_pub_del BEFORE DELETE ON {$table} FOR EACH ROW BEGIN IF {$publishedParent} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering child'; END IF; END");
        }
    }

    private function createVersionTable(string $tableName, string $id, string $parentId, string $parentTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($tableName, $id, $parentId, $parentTable): void {
            $table->char($id, 36)->primary();
            $table->char($parentId, 36);
            $table->char('hq_id', 36)->nullable();
            $table->unsignedInteger('version_number');
            $table->char('previous_version_id', 36)->nullable();
            $table->json('labels');
            $table->text('description')->nullable();
            $table->json('definition');
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
            $parentPrimary = rtrim($parentTable, 's').'_id';
            $table->foreign($parentId)->references($parentPrimary)->on($parentTable)->restrictOnDelete();
            $table->foreign('previous_version_id')->references($id)->on($tableName)->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique([$parentId, 'version_number'], "{$tableName}_number_unique");
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], "{$tableName}_effective_index");
        });
    }

    public function down(): void
    {
        foreach (['svc_option_rule', 'svc_eligibility_rule', 'svc_coverage_ref', 'svc_availability'] as $triggerPrefix) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerPrefix}_pub_ins");
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerPrefix}_pub_upd");
            DB::unprepared("DROP TRIGGER IF EXISTS {$triggerPrefix}_pub_del");
        }
        foreach (['service_type_versions', 'shipping_method_versions', 'service_option_versions', 'service_offering_versions'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_published_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_published_delete");
        }
        foreach (['service_legacy_mappings', 'service_availability_bindings', 'service_coverage_references', 'service_eligibility_rules', 'service_offering_option_rules', 'service_offering_versions', 'service_option_versions', 'shipping_method_versions', 'service_type_versions', 'service_offerings', 'service_options', 'shipping_methods', 'service_types'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
