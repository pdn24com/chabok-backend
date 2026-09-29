<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('last_mile_resolution_evidence', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('route_plan_id');
            $table->unsignedInteger('destination_gateway_node_id');
            $table->unsignedInteger('last_mile_node_id');
            $table->unsignedInteger('coverage_policy_id');
            $table->unsignedInteger('coverage_policy_version_id');
            $table->unsignedInteger('coverage_rule_id');
            $table->unsignedInteger('route_definition_version_id')->nullable();
            $table->json('resolution_input');
            $table->unsignedInteger('resolved_by');
            $table->timestamp('resolved_at', 6);
            $table->unique(['hq_id', 'consignment_id'], 'last_mile_evidence_consignment_unique');
            $table->index(['hq_id', 'route_plan_id'], 'last_mile_evidence_plan_fk');
            $table->index(['hq_id', 'last_mile_node_id'], 'last_mile_evidence_node_fk');
            $table->index(['hq_id', 'coverage_policy_version_id'], 'last_mile_evidence_coverage_version_fk');
            $table->index(['hq_id', 'coverage_rule_id'], 'last_mile_evidence_coverage_rule_fk');
            $table->index(['hq_id', 'route_definition_version_id'], 'last_mile_evidence_route_version_fk');
            $table->index(['resolved_by'], 'last_mile_resolution_evidence_resolved_by_foreign');
            $table->index(['hq_id', 'destination_gateway_node_id', 'resolved_at'], 'last_mile_evidence_gateway_index');
            $table->foreign(['hq_id', 'consignment_id'], 'last_mile_evidence_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_policy_version_id'], 'last_mile_evidence_coverage_version_fk')->references(['hq_id', 'id'])->on('coverage_policy_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_rule_id'], 'last_mile_evidence_coverage_rule_fk')->references(['hq_id', 'id'])->on('coverage_rules')->onDelete('restrict');
            $table->foreign(['hq_id', 'destination_gateway_node_id'], 'last_mile_evidence_gateway_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'last_mile_node_id'], 'last_mile_evidence_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_id'], 'last_mile_evidence_route_version_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_id'], 'last_mile_evidence_plan_fk')->references(['hq_id', 'id'])->on('route_plans')->onDelete('restrict');
            $table->foreign(['resolved_by'], 'last_mile_resolution_evidence_resolved_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('last_mile_resolution_evidence');
    }
};
