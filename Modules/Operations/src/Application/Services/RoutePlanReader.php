<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

final readonly class RoutePlanReader
{
    public function __construct(private \Modules\Operations\Application\Repositories\MovementRepository $plans)
    {
    }

    public function planVisibleAtNode(string $planId, string $consignmentId, string $nodeId): bool
    {
        return $this->plans->consignmentOriginatesAt($consignmentId, $nodeId) || $this->plans->planTouchesNode($planId, $nodeId);
    }

    public function routePlanItem(object $plan): array
    {
        $consignment = $this->plans->consignment($plan->hq_id, $plan->consignment_id);
        $definition = $this->plans->definition($plan->hq_id, $plan->route_definition_id);
        $versionStatus = $plan->route_definition_version_id === null ? null : $this->plans->routeVersionStatus($plan->hq_id, $plan->route_definition_version_id);
        $evidence = $this->plans->resolutionEvidence($plan->hq_id, $plan->route_plan_id);
        $legs = array_map(fn($leg): array => [
            'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
            'source_route_definition_version_leg_id' => $leg->source_route_definition_version_leg_id === null ? null : (string) $leg->source_route_definition_version_leg_id,
            'leg_order' => (int) $leg->leg_order,
            'origin_node' => [
                'node_id' => (string) $leg->origin_node_id,
                'node_code' => (string) $leg->origin_node_code,
                'node_title' => (string) $leg->origin_node_title,
            ],
            'destination_node' => [
                'node_id' => (string) $leg->destination_node_id,
                'node_code' => (string) $leg->destination_node_code,
                'node_title' => (string) $leg->destination_node_title,
            ],
            'status' => (string) $leg->status,
            'routed_at' => $leg->routed_at,
            'received_at' => $leg->received_at,
        ], $this->plans->planLegs($plan->route_plan_id));
        $configurationState = $evidence === null || $versionStatus === null ? 'UNAVAILABLE' : ($versionStatus === 'SUPERSEDED' || $evidence->coverage_version_status === 'SUPERSEDED' ? 'STALE' : ($versionStatus === 'PUBLISHED' && $evidence->coverage_version_status === 'PUBLISHED' ? 'AVAILABLE' : 'UNAVAILABLE'));
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
                'coverage_policy_code' => (string) $evidence->policy_code,
                'coverage_policy_title' => (string) $evidence->policy_title,
                'coverage_policy_version_id' => (string) $evidence->coverage_policy_version_id,
                'coverage_policy_version_number' => (int) $evidence->coverage_version_number,
                'coverage_rule_id' => (string) $evidence->coverage_rule_id,
                'coverage_criterion_type' => (string) $evidence->coverage_criterion_type,
                'coverage_priority' => (int) $evidence->coverage_priority,
                'resolution_input' => json_decode((string) $evidence->resolution_input, true, 512, JSON_THROW_ON_ERROR),
                'matched_geography_evidence' => $this->jsonObject($evidence->matched_geography_evidence),
                'matched_postal_evidence' => $this->jsonObject($evidence->matched_postal_evidence),
                'matched_geometry_evidence' => $this->jsonObject($evidence->matched_geometry_evidence),
                'destination_gateway' => [
                    'node_id' => (string) $evidence->destination_gateway_node_id,
                    'node_code' => (string) $evidence->gateway_code,
                    'node_title' => (string) $evidence->gateway_title,
                ],
                'route_definition_id' => (string) $evidence->route_definition_id,
                'route_definition_version_id' => (string) $evidence->route_definition_version_id,
                'route_purpose' => (string) $evidence->route_purpose,
                'ordered_route_legs' => json_decode((string) $evidence->ordered_route_legs, true, 512, JSON_THROW_ON_ERROR),
                'offering_version_id' => $evidence->offering_version_id === null ? null : (string) $evidence->offering_version_id,
                'resolved_at' => $evidence->resolved_at,
            ],
            'legs' => $legs,
        ];
    }

    public function jsonObject(mixed $value): ?array
    {
        return $value === null ? null : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    }
}
