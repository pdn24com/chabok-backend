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
        Schema::table('pickup_tasks', function (Blueprint $table): void {
            $table->timestamp('assigned_at', 6)->nullable()->after('version');
            $table->timestamp('started_at', 6)->nullable()->after('assigned_at');
            $table->timestamp('failed_at', 6)->nullable()->after('completed_at');
        });

        Schema::create('route_plan_resolution_evidence', function (Blueprint $table): void {
            $table->char('resolution_evidence_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('consignment_id', 36);
            $table->char('route_plan_id', 36);
            $table->char('coverage_policy_id', 36);
            $table->char('coverage_policy_version_id', 36);
            $table->char('coverage_rule_id', 36);
            $table->string('coverage_criterion_type', 32);
            $table->integer('coverage_priority');
            $table->json('resolution_input');
            $table->json('matched_geography_evidence')->nullable();
            $table->json('matched_postal_evidence')->nullable();
            $table->json('matched_geometry_evidence')->nullable();
            $table->char('destination_gateway_node_id', 36);
            $table->char('route_definition_id', 36);
            $table->char('route_definition_version_id', 36);
            $table->enum('route_purpose', ['TRUNK', 'LAST_MILE']);
            $table->json('ordered_route_legs');
            $table->char('offering_version_id', 36)->nullable();
            $table->timestamp('resolved_at', 6);
            $table->timestamp('created_at', 6)->useCurrent();

            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'consignment_id'], 'route_resolution_consignment_fk')
                ->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_plan_id'], 'route_resolution_plan_fk')
                ->references(['hq_id', 'route_plan_id'])->on('route_plans')->restrictOnDelete();
            $table->foreign(['hq_id', 'coverage_policy_id'], 'route_resolution_policy_fk')
                ->references(['hq_id', 'coverage_policy_id'])->on('coverage_policies')->restrictOnDelete();
            $table->foreign(['hq_id', 'coverage_policy_version_id'], 'route_resolution_coverage_version_fk')
                ->references(['hq_id', 'coverage_policy_version_id'])->on('coverage_policy_versions')->restrictOnDelete();
            $table->foreign(['hq_id', 'coverage_rule_id'], 'route_resolution_rule_fk')
                ->references(['hq_id', 'coverage_rule_id'])->on('coverage_rules')->restrictOnDelete();
            $table->foreign(['hq_id', 'destination_gateway_node_id'], 'route_resolution_gateway_fk')
                ->references(['hq_id', 'node_id'])->on('nodes')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_id'], 'route_resolution_definition_fk')
                ->references(['hq_id', 'route_definition_id'])->on('route_definitions')->restrictOnDelete();
            $table->foreign(['hq_id', 'route_definition_version_id'], 'route_resolution_route_version_fk')
                ->references(['hq_id', 'route_definition_version_id'])->on('route_definition_versions')->restrictOnDelete();
            $table->foreign('offering_version_id')->references('service_offering_version_id')
                ->on('service_offering_versions')->restrictOnDelete();

            $table->unique(['hq_id', 'resolution_evidence_id'], 'route_resolution_hq_id_unique');
            $table->unique('route_plan_id', 'route_resolution_plan_unique');
            $table->index(
                ['hq_id', 'destination_gateway_node_id', 'resolved_at'],
                'route_resolution_gateway_index',
            );
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER route_plan_resolution_evidence_immutable_update BEFORE UPDATE ON route_plan_resolution_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable route resolution evidence'");
            DB::unprepared("CREATE TRIGGER route_plan_resolution_evidence_immutable_delete BEFORE DELETE ON route_plan_resolution_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable route resolution evidence'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS route_plan_resolution_evidence_immutable_update');
            DB::unprepared('DROP TRIGGER IF EXISTS route_plan_resolution_evidence_immutable_delete');
        }

        Schema::dropIfExists('route_plan_resolution_evidence');
        Schema::table('pickup_tasks', function (Blueprint $table): void {
            $table->dropColumn(['assigned_at', 'started_at', 'failed_at']);
        });
    }
};
