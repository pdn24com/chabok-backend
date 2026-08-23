<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_runs', function (Blueprint $table): void {
            $table->unique(['hq_id', 'route_plan_leg_id'], 'transport_runs_leg_unique');
        });
        if (Schema::hasIndex('transport_runs', 'transport_runs_leg_fk_index')) {
            Schema::table('transport_runs', function (Blueprint $table): void {
                $table->dropIndex('transport_runs_leg_fk_index');
            });
        }

        Schema::create('transport_run_history', function (Blueprint $table): void {
            $table->char('transport_run_history_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('transport_run_id', 36);
            $table->unsignedInteger('event_sequence');
            $table->enum('event_type', ['CREATED', 'LOADED', 'DEPARTED', 'ARRIVED', 'CLOSED']);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->char('node_id', 36);
            $table->char('actor_id', 36);
            $table->unsignedInteger('aggregate_version');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 6)->useCurrent();
            $table->foreign(['hq_id', 'transport_run_id'], 'transport_run_history_run_fk')->references(['hq_id', 'transport_run_id'])->on('transport_runs')->restrictOnDelete();
            $table->foreign(['hq_id', 'node_id'], 'transport_run_history_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign('actor_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['transport_run_id', 'event_sequence'], 'transport_run_history_sequence_unique');
            $table->index(['hq_id', 'transport_run_id', 'occurred_at'], 'transport_run_history_timeline_index');
        });

        Schema::create('last_mile_resolution_evidence', function (Blueprint $table): void {
            $table->char('last_mile_resolution_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('route_plan_id', 36);
            $table->char('destination_gateway_node_id', 36);
            $table->char('last_mile_node_id', 36);
            $table->char('coverage_policy_id', 36);
            $table->char('coverage_policy_version_id', 36);
            $table->char('coverage_rule_id', 36);
            $table->char('route_definition_version_id', 36)->nullable();
            $table->json('resolution_input');
            $table->char('resolved_by', 36);
            $table->timestamp('resolved_at', 6);
            $table->foreign(['hq_id', 'consignment_id'], 'last_mile_evidence_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_plan_id'], 'last_mile_evidence_plan_fk')->references(['hq_id', 'route_plan_id'])->on('route_plans')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_gateway_node_id'], 'last_mile_evidence_gateway_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'last_mile_node_id'], 'last_mile_evidence_node_fk')->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'coverage_policy_version_id'], 'last_mile_evidence_coverage_version_fk')->references(['hq_id', 'coverage_policy_version_id'])->on('coverage_policy_versions')->restrictOnDelete();
            $table->foreign(['hq_id', 'coverage_rule_id'], 'last_mile_evidence_coverage_rule_fk')->references(['hq_id', 'coverage_rule_id'])->on('coverage_rules')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_version_id'], 'last_mile_evidence_route_version_fk')->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
            $table->foreign('resolved_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'consignment_id'], 'last_mile_evidence_consignment_unique');
            $table->index(['hq_id', 'destination_gateway_node_id', 'resolved_at'], 'last_mile_evidence_gateway_index');
        });

        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->unsignedInteger('attempt_number')->default(1)->after('status');
            $table->char('last_mile_resolution_id', 36)->nullable()->after('manifest_id');
            $table->foreign('last_mile_resolution_id', 'delivery_tasks_last_mile_evidence_fk')->references('last_mile_resolution_id')->on('last_mile_resolution_evidence')->restrictOnDelete();
        });

        Schema::create('delivery_task_history', function (Blueprint $table): void {
            $table->char('delivery_task_history_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('delivery_task_id', 36);
            $table->char('consignment_id', 36);
            $table->unsignedInteger('event_sequence');
            $table->enum('event_type', ['CREATED', 'ASSIGNED', 'REASSIGNED', 'ACTIVATED', 'COMPLETED', 'FAILED', 'RETRY_REQUESTED']);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->unsignedInteger('attempt_number');
            $table->char('assigned_driver_id', 36)->nullable();
            $table->char('actor_id', 36);
            $table->string('reason_code', 80)->nullable();
            $table->string('safe_note', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at', 6)->useCurrent();
            $table->foreign(['hq_id', 'delivery_task_id'], 'delivery_task_history_task_fk')->references(['hq_id', 'delivery_task_id'])->on('delivery_tasks')->restrictOnDelete();
            $table->foreign(['hq_id', 'consignment_id'], 'delivery_task_history_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'assigned_driver_id'], 'delivery_task_history_driver_fk')->references(['hq_id', 'driver_id'])->on('drivers')->restrictOnDelete();
            $table->foreign('actor_id')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['delivery_task_id', 'event_sequence'], 'delivery_task_history_sequence_unique');
            $table->index(['hq_id', 'delivery_task_id', 'occurred_at'], 'delivery_task_history_timeline_index');
        });

        foreach (['transport_run_history', 'last_mile_resolution_evidence', 'delivery_task_history'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable_update BEFORE UPDATE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable transport delivery history'");
            DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable transport delivery history'");
        }
    }

    public function down(): void
    {
        foreach (['delivery_task_history', 'last_mile_resolution_evidence', 'transport_run_history'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }
        Schema::dropIfExists('delivery_task_history');
        Schema::table('delivery_tasks', function (Blueprint $table): void {
            $table->dropForeign('delivery_tasks_last_mile_evidence_fk');
            $table->dropColumn(['attempt_number', 'last_mile_resolution_id']);
        });
        Schema::dropIfExists('last_mile_resolution_evidence');
        Schema::dropIfExists('transport_run_history');
        Schema::table('transport_runs', function (Blueprint $table): void {
            // Keep the pre-existing composite FK supported while removing the
            // uniqueness constraint introduced by this migration.
            $table->index(['hq_id', 'route_plan_leg_id'], 'transport_runs_leg_fk_index');
        });
        Schema::table('transport_runs', function (Blueprint $table): void {
            $table->dropUnique('transport_runs_leg_unique');
        });
    }
};
