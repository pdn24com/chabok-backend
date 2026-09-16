<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestRoutePlanner
{
    public function __construct(
        private \Modules\Operations\Application\Contracts\ManifestRouteAccess $routeState,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
        private \Modules\Operations\Application\Contracts\ManifestDirectoryReader $directory,
        private \Modules\Operations\Application\CoveragePolicyService $coverage,
        private \Modules\Operations\Application\RouteDefinitionService $routes,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Manifest\Application\Services\ManifestTransitionRecorder $manifestTransitionRecorder,
    )
    {
    }

    public function ensureRoute(AuthenticatedPrincipal $actor, string $node, object $p, string $correlationId): array
    {
        $plan = $this->routeState->lockActivePlan($actor->hqId, $p->consignment_id);
        if ($plan === null) {
            $plan = $this->createPlan($actor, $node, (string) $p->consignment_id, $correlationId);
        }
        $leg = $this->routeState->lockNextOriginLeg($actor->hqId, $plan->route_plan_id, $node);
        if ($leg === null) {
            throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'No next Route Leg is available.');
        }
        if ((int) $leg->leg_order > 1 && !$this->routeState->previousLegReceived($plan->route_plan_id, $leg->leg_order)) {
            throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'The preceding Route Leg is not received.');
        }
        if ((string) $leg->status === 'PENDING') {
            $this->routeState->updateLeg($leg->route_plan_leg_id, ['status' => 'ROUTED', 'routed_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
            $this->routeState->updatePlan($plan->route_plan_id, ['status' => 'IN_PROGRESS', 'version' => (int) $plan->version + 1, 'updated_at' => $this->clock->now()]);
        }
        return [
            'route_plan_id' => (string) $plan->route_plan_id,
            'route_definition_version_id' => (string) $plan->route_definition_version_id,
            'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
            'route_definition_version_leg_id' => (string) $leg->source_route_definition_version_leg_id,
        ];
    }

    public function createPlan(AuthenticatedPrincipal $actor, string $node, string $consignmentId, string $correlationId): object
    {
        $c = $this->consignmentState->lockAtPickupNode($actor->hqId, $consignmentId, $node);
        if ($c === null) {
            throw new ApiException(ApiErrorCode::RoutePlanUnavailable, 422, 'The Consignment cannot resolve a Route Plan at this Node.');
        }
        $input = [];
        if ($c->receiver_city_id !== null) {
            $input['city_id'] = (string) $c->receiver_city_id;
            $province = $this->directory->provinceForActiveCity($c->receiver_city_id);
            if ($province) {
                $input['province_id'] = (string) $province;
            }
        }
        if (preg_match('/^\d{10}$/', (string) $c->receiver_postal_code) === 1) {
            $input['postal_code'] = (string) $c->receiver_postal_code;
        }
        if ($c->receiver_latitude !== null && $c->receiver_longitude !== null) {
            $input['latitude'] = (float) $c->receiver_latitude;
            $input['longitude'] = (float) $c->receiver_longitude;
        }
        if ($input === []) {
            throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'Canonical destination geography is unavailable.');
        }
        $at = $this->clock->now();
        $coverage = $this->coverage->resolve((string) $actor->hqId, 'DESTINATION_GATEWAY', $input, $c->service_offering_version_id, $at);
        $route = $this->routes->resolve((string) $actor->hqId, 'TRUNK', $node, (string) $coverage['target_node_id'], $c->service_offering_version_id, $at);
        $legs = $this->routeState->sourceLegs($actor->hqId, $route['route_definition_version_id']);
        if ($legs === []) {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The published Route Version is unavailable.');
        }
        $id = $this->identifiers->uuid();
        $this->routeState->insertPlan([
            'route_plan_id' => $id,
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
            'route_definition_id' => $route['route_definition_id'],
            'route_definition_version_id' => $route['route_definition_version_id'],
            'status' => 'PLANNED',
            'active_slot' => hash('sha256', $actor->hqId . '|' . $consignmentId . '|ACTIVE'),
            'version' => 1,
            'created_by' => $actor->userId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $ordered = [];
        foreach ($legs as $leg) {
            $legacy = $this->routeState->legacyLegId($actor->hqId, $route['route_definition_id'], $leg->leg_order);
            if ($legacy === null) {
                throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'Published Route Leg evidence is unavailable.');
            }
            $this->routeState->insertPlanLeg([
                'route_plan_leg_id' => $this->identifiers->uuid(),
                'hq_id' => $actor->hqId,
                'route_plan_id' => $id,
                'source_route_definition_leg_id' => $legacy,
                'source_route_definition_version_leg_id' => $leg->route_definition_version_leg_id,
                'leg_order' => $leg->leg_order,
                'origin_node_id' => $leg->origin_node_id,
                'destination_node_id' => $leg->destination_node_id,
                'status' => 'PENDING',
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $ordered[] = [
                'route_definition_version_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ];
        }
        $matched = (array) $coverage['matched_evidence'];
        $this->routeState->insertResolutionEvidence([
            'resolution_evidence_id' => $this->identifiers->uuid(),
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
            'route_plan_id' => $id,
            'coverage_policy_id' => $coverage['coverage_policy_id'],
            'coverage_policy_version_id' => $coverage['coverage_policy_version_id'],
            'coverage_rule_id' => $coverage['coverage_rule_id'],
            'coverage_criterion_type' => $coverage['criterion_type'],
            'coverage_priority' => $coverage['priority'],
            'resolution_input' => json_encode($coverage['input'], JSON_THROW_ON_ERROR),
            'matched_geography_evidence' => isset($matched['geography']) ? json_encode($matched['geography'], JSON_THROW_ON_ERROR) : null,
            'matched_postal_evidence' => isset($matched['postal']) ? json_encode($matched['postal'], JSON_THROW_ON_ERROR) : null,
            'matched_geometry_evidence' => isset($matched['geometry']) ? json_encode($matched['geometry'], JSON_THROW_ON_ERROR) : null,
            'destination_gateway_node_id' => $coverage['target_node_id'],
            'route_definition_id' => $route['route_definition_id'],
            'route_definition_version_id' => $route['route_definition_version_id'],
            'route_purpose' => 'TRUNK',
            'ordered_route_legs' => json_encode($ordered, JSON_THROW_ON_ERROR),
            'offering_version_id' => $c->service_offering_version_id,
            'resolved_at' => $at,
            'created_at' => $at,
        ]);
        $this->audit->write($actor->hqId, $actor->userId, 'ROUTE_PLAN_CREATED', 'ROUTE_PLAN', $id, $correlationId);
        $this->manifestTransitionRecorder->commandEvent($actor, $id, $consignmentId, 'ROUTE_PLAN_CREATED', 'PLANNED', $correlationId);
        return $this->routeState->plan($id);
    }
}
