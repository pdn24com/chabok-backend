<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_plan_resolution_evidence', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('route_plan_id');
            $table->unsignedInteger('coverage_policy_id');
            $table->unsignedInteger('coverage_policy_version_id');
            $table->unsignedInteger('coverage_rule_id');
            $table->string('coverage_criterion_type', 32);
            $table->integer('coverage_priority');
            $table->json('resolution_input');
            $table->json('matched_geography_evidence')->nullable();
            $table->json('matched_postal_evidence')->nullable();
            $table->json('matched_geometry_evidence')->nullable();
            $table->unsignedInteger('destination_gateway_node_id');
            $table->unsignedInteger('route_definition_id');
            $table->unsignedInteger('route_definition_version_id');
            $table->enum('route_purpose', ['TRUNK', 'LAST_MILE']);
            $table->json('ordered_route_legs');
            $table->unsignedInteger('offering_version_id')->nullable();
            $table->timestamp('resolved_at', 6);
            $table->timestamp('created_at', 6)->useCurrent();
            $table->unique(['hq_id', 'id'], 'route_resolution_hq_id_unique');
            $table->unique(['route_plan_id'], 'route_resolution_plan_unique');
            $table->index(['hq_id', 'consignment_id'], 'route_resolution_consignment_fk');
            $table->index(['hq_id', 'route_plan_id'], 'route_resolution_plan_fk');
            $table->index(['hq_id', 'coverage_policy_id'], 'route_resolution_policy_fk');
            $table->index(['hq_id', 'coverage_policy_version_id'], 'route_resolution_coverage_version_fk');
            $table->index(['hq_id', 'coverage_rule_id'], 'route_resolution_rule_fk');
            $table->index(['hq_id', 'route_definition_id'], 'route_resolution_definition_fk');
            $table->index(['hq_id', 'route_definition_version_id'], 'route_resolution_route_version_fk');
            $table->index(['offering_version_id'], 'route_plan_resolution_evidence_offering_version_id_foreign');
            $table->index(['hq_id', 'destination_gateway_node_id', 'resolved_at'], 'route_resolution_gateway_index');
            $table->foreign(['hq_id', 'consignment_id'], 'route_resolution_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_policy_id'], 'route_resolution_policy_fk')->references(['hq_id', 'id'])->on('coverage_policies')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_policy_version_id'], 'route_resolution_coverage_version_fk')->references(['hq_id', 'id'])->on('coverage_policy_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'coverage_rule_id'], 'route_resolution_rule_fk')->references(['hq_id', 'id'])->on('coverage_rules')->onDelete('restrict');
            $table->foreign(['hq_id', 'destination_gateway_node_id'], 'route_resolution_gateway_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_id'], 'route_resolution_definition_fk')->references(['hq_id', 'id'])->on('route_definitions')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_id'], 'route_resolution_route_version_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_plan_id'], 'route_resolution_plan_fk')->references(['hq_id', 'id'])->on('route_plans')->onDelete('restrict');
            $table->foreign(['hq_id'], 'route_plan_resolution_evidence_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['offering_version_id'], 'route_plan_resolution_evidence_offering_version_id_foreign')->references(['id'])->on('service_offering_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_plan_resolution_evidence');
    }
};
