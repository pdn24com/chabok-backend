<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;

final class RoutePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->resource;
        $consignment = $plan->consignment;
        $definition = $plan->definition;
        $versionStatus = $plan->definitionVersion?->status;
        $evidence = $plan->evidence;
        if ($evidence !== null && ($evidence->policy === null || $evidence->coverageVersion === null || $evidence->gateway === null)) {
            $evidence = null;
        }
        $legs = $plan->legs->map(fn ($leg): array => [
            'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
            'source_route_definition_version_leg_id' => $leg->source_route_definition_version_leg_id === null ? null : (string) $leg->source_route_definition_version_leg_id,
            'leg_order' => (int) $leg->leg_order,
            'origin_node' => [
                'node_id' => (string) $leg->origin_node_id,
                'node_code' => (string) $leg->originNode->node_code,
                'node_title' => (string) $leg->originNode->node_title,
            ],
            'destination_node' => [
                'node_id' => (string) $leg->destination_node_id,
                'node_code' => (string) $leg->destinationNode->node_code,
                'node_title' => (string) $leg->destinationNode->node_title,
            ],
            'status' => (string) $leg->status,
            'routed_at' => $leg->routed_at,
            'received_at' => $leg->received_at,
        ])->all();
        $configurationState = 'UNAVAILABLE';
        if ($evidence !== null && $versionStatus !== null) {
            if ($versionStatus === ConfigVersionStatus::Superseded->value || $evidence->coverageVersion->status === ConfigVersionStatus::Superseded->value) {
                $configurationState = 'STALE';
            } elseif ($versionStatus === ConfigVersionStatus::Published->value && $evidence->coverageVersion->status === ConfigVersionStatus::Published->value) {
                $configurationState = 'AVAILABLE';
            }
        }

        return [
            'route_plan_id' => (string) $plan->route_plan_id,
            'consignment_id' => (string) $plan->consignment_id,
            'consignment_number' => $consignment === null ? null : (string) $consignment->consignment_number,
            'route_definition_id' => (string) $plan->route_definition_id,
            'route_definition_version_id' => $plan->route_definition_version_id === null ? null : (string) $plan->route_definition_version_id,
            'route_code' => $definition === null ? null : (string) $definition->route_code,
            'route_title' => $definition === null ? null : (string) $definition->route_title,
            'status' => (string) $plan->status,
            'configuration_state' => $configurationState,
            'version' => (int) $plan->version,
            'created_at' => $plan->created_at,
            'resolution_evidence' => $evidence === null ? null : [
                'coverage_policy_id' => (string) $evidence->coverage_policy_id,
                'coverage_policy_code' => (string) $evidence->policy->policy_code,
                'coverage_policy_title' => (string) $evidence->policy->policy_title,
                'coverage_policy_version_id' => (string) $evidence->coverage_policy_version_id,
                'coverage_policy_version_number' => (int) $evidence->coverageVersion->version_number,
                'coverage_rule_id' => (string) $evidence->coverage_rule_id,
                'coverage_criterion_type' => (string) $evidence->coverage_criterion_type,
                'coverage_priority' => (int) $evidence->coverage_priority,
                'resolution_input' => $evidence->resolution_input,
                'matched_geography_evidence' => $evidence->matched_geography_evidence,
                'matched_postal_evidence' => $evidence->matched_postal_evidence,
                'matched_geometry_evidence' => $evidence->matched_geometry_evidence,
                'destination_gateway' => [
                    'node_id' => (string) $evidence->destination_gateway_node_id,
                    'node_code' => (string) $evidence->gateway->node_code,
                    'node_title' => (string) $evidence->gateway->node_title,
                ],
                'route_definition_id' => (string) $evidence->route_definition_id,
                'route_definition_version_id' => (string) $evidence->route_definition_version_id,
                'route_purpose' => (string) $evidence->route_purpose,
                'ordered_route_legs' => $evidence->ordered_route_legs,
                'offering_version_id' => $evidence->offering_version_id === null ? null : (string) $evidence->offering_version_id,
                'resolved_at' => $evidence->resolved_at,
            ],
            'legs' => $legs,
        ];

    }
}
