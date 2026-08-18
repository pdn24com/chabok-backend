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
        Schema::create('commitment_schedules', function (Blueprint $table): void {
            $table->char('commitment_schedule_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->string('owner_key', 36);
            $table->string('code', 80);
            $table->string('title', 200);
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'ARCHIVED'])->default('ACTIVE');
            $table->char('created_by', 36);
            $table->timestamps(6);
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['owner_key', 'code'], 'commitment_schedules_owner_code_unique');
            $table->unique(['hq_id', 'commitment_schedule_id'], 'commitment_schedules_hq_unique');
            $table->index(['hq_id', 'status', 'code'], 'commitment_schedules_tenant_list_index');
        });

        Schema::create('commitment_schedule_versions', function (Blueprint $table): void {
            $table->char('commitment_schedule_version_id', 36)->primary();
            $table->char('commitment_schedule_id', 36);
            $table->char('hq_id', 36);
            $table->unsignedInteger('version_number');
            $table->char('previous_version_id', 36)->nullable();
            $table->enum('status', self::VERSION_STATUSES)->default('DRAFT');
            $table->string('timezone', 80)->default('Asia/Tehran');
            $table->string('calendar_code', 80)->default('IR_STANDARD');
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
            $table->foreign(['hq_id', 'commitment_schedule_id'], 'commitment_schedule_versions_identity_fk')
                ->references(['hq_id', 'commitment_schedule_id'])->on('commitment_schedules')->restrictOnDelete();
            $table->foreign('previous_version_id')->references('commitment_schedule_version_id')->on('commitment_schedule_versions')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('published_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['commitment_schedule_id', 'version_number'], 'commitment_schedule_version_number_unique');
            $table->index(['hq_id', 'status', 'valid_from', 'valid_to'], 'commitment_schedule_effective_index');
        });

        Schema::create('commitment_schedule_windows', function (Blueprint $table): void {
            $table->char('commitment_schedule_window_id', 36)->primary();
            $table->char('commitment_schedule_version_id', 36);
            $table->string('window_code', 80);
            $table->enum('window_type', ['PICKUP', 'DELIVERY']);
            $table->string('label_fa', 200);
            $table->time('start_time');
            $table->time('end_time');
            $table->time('booking_cutoff_time');
            $table->json('applicable_weekdays');
            $table->smallInteger('day_offset')->default(0);
            $table->boolean('active')->default(true);
            $table->foreign('commitment_schedule_version_id', 'commitment_schedule_windows_version_fk')
                ->references('commitment_schedule_version_id')->on('commitment_schedule_versions')->cascadeOnDelete();
            $table->unique(['commitment_schedule_version_id', 'window_code'], 'commitment_schedule_window_code_unique');
            $table->index(['commitment_schedule_version_id', 'window_type', 'active'], 'commitment_schedule_window_lookup_index');
        });

        Schema::create('commitment_schedule_scopes', function (Blueprint $table): void {
            $table->char('commitment_schedule_scope_id', 36)->primary();
            $table->char('commitment_schedule_version_id', 36);
            $table->char('hq_id', 36);
            $table->enum('scope_type', ['HQ', 'NODE']);
            $table->char('node_id', 36)->nullable();
            $table->foreign('commitment_schedule_version_id', 'commitment_schedule_scopes_version_fk')
                ->references('commitment_schedule_version_id')->on('commitment_schedule_versions')->cascadeOnDelete();
            $table->foreign(['hq_id', 'node_id'], 'commitment_schedule_scopes_node_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->unique(['commitment_schedule_version_id', 'scope_type', 'node_id'], 'commitment_schedule_scope_unique');
            $table->index(['hq_id', 'scope_type', 'node_id'], 'commitment_schedule_scope_lookup_index');
        });

        Schema::create('service_offering_commitment_bindings', function (Blueprint $table): void {
            $table->char('offering_commitment_binding_id', 36)->primary();
            $table->char('service_offering_version_id', 36)
                ->unique('offering_commitment_binding_offering_unique');
            $table->char('commitment_schedule_version_id', 36);
            $table->enum('pickup_mode', ['NONE', 'SELECTABLE_WINDOW', 'COMPUTED']);
            $table->enum('delivery_mode', ['NONE', 'SELECTABLE_WINDOW', 'COMPUTED']);
            $table->unsignedInteger('duration_value')->nullable();
            $table->enum('duration_unit', ['MINUTE', 'HOUR', 'DAY'])->nullable();
            $table->enum('duration_anchor', ['CONSIGNMENT_CREATED', 'PICKUP_COMMITMENT_START', 'PICKUP_COMMITMENT_END', 'PICKUP_COMPLETED'])->nullable();
            $table->foreign('service_offering_version_id', 'offering_commitment_binding_offering_fk')
                ->references('service_offering_version_id')->on('service_offering_versions')->cascadeOnDelete();
            $table->foreign('commitment_schedule_version_id', 'offering_commitment_binding_schedule_fk')
                ->references('commitment_schedule_version_id')->on('commitment_schedule_versions')->restrictOnDelete();
            $table->index(['commitment_schedule_version_id', 'pickup_mode', 'delivery_mode'], 'offering_commitment_schedule_lookup_index');
        });

        $versionContentEqual = '(NEW.commitment_schedule_id <=> OLD.commitment_schedule_id AND NEW.hq_id <=> OLD.hq_id AND NEW.version_number <=> OLD.version_number AND NEW.previous_version_id <=> OLD.previous_version_id AND NEW.timezone <=> OLD.timezone AND NEW.calendar_code <=> OLD.calendar_code AND NEW.valid_from <=> OLD.valid_from AND NEW.valid_to <=> OLD.valid_to AND NEW.content_digest <=> OLD.content_digest)';
        DB::unprepared("CREATE TRIGGER commitment_schedule_versions_published_update BEFORE UPDATE ON commitment_schedule_versions FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (((OLD.status = 'PUBLISHED' AND NEW.status = 'SUPERSEDED') OR (OLD.status = 'SUPERSEDED' AND NEW.status = 'ARCHIVED')) AND {$versionContentEqual}) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule version'; END IF; END");
        DB::unprepared("CREATE TRIGGER commitment_schedule_versions_published_delete BEFORE DELETE ON commitment_schedule_versions FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule version'; END IF; END");

        foreach (['commitment_schedule_windows' => 'commitment_window', 'commitment_schedule_scopes' => 'commitment_scope'] as $table => $prefix) {
            DB::unprepared("CREATE TRIGGER {$prefix}_pub_ins BEFORE INSERT ON {$table} FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM commitment_schedule_versions v WHERE v.commitment_schedule_version_id = NEW.commitment_schedule_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule child'; END IF; END");
            DB::unprepared("CREATE TRIGGER {$prefix}_pub_upd BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM commitment_schedule_versions v WHERE v.commitment_schedule_version_id = OLD.commitment_schedule_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule child'; END IF; END");
            DB::unprepared("CREATE TRIGGER {$prefix}_pub_del BEFORE DELETE ON {$table} FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM commitment_schedule_versions v WHERE v.commitment_schedule_version_id = OLD.commitment_schedule_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment schedule child'; END IF; END");
        }
        foreach (['ins' => 'NEW', 'upd' => 'OLD', 'del' => 'OLD'] as $suffix => $record) {
            $operation = $suffix === 'ins' ? 'INSERT' : ($suffix === 'upd' ? 'UPDATE' : 'DELETE');
            DB::unprepared("CREATE TRIGGER offering_commitment_binding_pub_{$suffix} BEFORE {$operation} ON service_offering_commitment_bindings FOR EACH ROW BEGIN IF EXISTS (SELECT 1 FROM service_offering_versions v WHERE v.service_offering_version_id = {$record}.service_offering_version_id AND v.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published Service Offering commitment binding'; END IF; END");
        }
    }

    public function down(): void
    {
        foreach (['offering_commitment_binding_pub_ins', 'offering_commitment_binding_pub_upd', 'offering_commitment_binding_pub_del', 'commitment_window_pub_ins', 'commitment_window_pub_upd', 'commitment_window_pub_del', 'commitment_scope_pub_ins', 'commitment_scope_pub_upd', 'commitment_scope_pub_del', 'commitment_schedule_versions_published_update', 'commitment_schedule_versions_published_delete'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
        Schema::dropIfExists('service_offering_commitment_bindings');
        Schema::dropIfExists('commitment_schedule_scopes');
        Schema::dropIfExists('commitment_schedule_windows');
        Schema::dropIfExists('commitment_schedule_versions');
        Schema::dropIfExists('commitment_schedules');
    }
};
