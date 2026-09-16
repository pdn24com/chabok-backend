<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\PlanConsignmentRoute;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PlanConsignmentRouteHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\MovementAccessGuard $movementAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Operations\Application\Repositories\MovementRepository $plans,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\DestinationResolutionInput $destinationResolutionInput,
        private \Modules\Operations\Application\CoveragePolicyService $coverage,
        private \Modules\Operations\Application\RouteDefinitionService $routes,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Operations\Application\Services\MovementRecorder $movementRecorder,
        private \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler $getRoutePlan,
    )
    {
    }

    public function handle(PlanConsignmentRouteCommand $command): PlanConsignmentRouteResult
    {
        return new PlanConsignmentRouteResult($this->execute($command->actor, $command->nodeId, $command->consignmentId, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $correlationId): array
    {
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $correlationId): string {
            $consignment = $this->ledger->lockAtPickupNode((string) $actor->hqId, $consignmentId, $nodeId);
            if ($consignment === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $existing = $this->plans->activePlan($actor->hqId, $consignmentId);
            if ($existing !== null) {
                return (string) $existing->route_plan_id;
            }
            $resolvedAt = $this->clock->now();
            $resolutionInput = $this->destinationResolutionInput->destinationResolutionInput($consignment);
            $coverage = $this->coverage->resolve((string) $actor->hqId, 'DESTINATION_GATEWAY', $resolutionInput, $consignment->service_offering_version_id === null ? null : (string) $consignment->service_offering_version_id, $resolvedAt);
            $route = $this->routes->resolve((string) $actor->hqId, 'TRUNK', $nodeId, (string) $coverage['target_node_id'], $consignment->service_offering_version_id === null ? null : (string) $consignment->service_offering_version_id, $resolvedAt);
            $legs = $this->plans->sourceLegs($actor->hqId, $route['route_definition_version_id']);
            if ($legs === []) {
                throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The published Route Version is unavailable.');
            }
            $definitionId = (string) $route['route_definition_id'];
            $orderedLegEvidence = array_map(fn($leg): array => [
                'route_definition_version_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ], $legs);
            $id = $this->identifiers->uuid();
            $this->plans->insertPlan([
                'route_plan_id' => $id,
                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'route_definition_id' => $definitionId,
                'route_definition_version_id' => $route['route_definition_version_id'],
                'status' => 'PLANNED',
                'active_slot' => hash('sha256', "{$actor->hqId}|{$consignmentId}|ACTIVE"),
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ]);
            foreach ($legs as $leg) {
                $legacyLegId = $this->plans->legacyLegId($actor->hqId, (int) $leg->leg_order, $definitionId);
                if ($legacyLegId === null) {
                    throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'The published Route Version leg snapshot is unavailable.');
                }
                $this->plans->insertLeg([
                    'route_plan_leg_id' => $this->identifiers->uuid(),
                    'hq_id' => $actor->hqId,
                    'route_plan_id' => $id,
                    'source_route_definition_leg_id' => $legacyLegId,
                    'source_route_definition_version_leg_id' => $leg->route_definition_version_leg_id,
                    'leg_order' => $leg->leg_order,
                    'origin_node_id' => $leg->origin_node_id,
                    'destination_node_id' => $leg->destination_node_id,
                    'status' => 'PENDING',
                    'created_at' => $resolvedAt,
                    'updated_at' => $resolvedAt,
                ]);
            }
            $matched = (array) $coverage['matched_evidence'];
            $this->plans->insertEvidence([
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
                'route_definition_id' => $definitionId,
                'route_definition_version_id' => $route['route_definition_version_id'],
                'route_purpose' => 'TRUNK',
                'ordered_route_legs' => json_encode($orderedLegEvidence, JSON_THROW_ON_ERROR),
                'offering_version_id' => $consignment->service_offering_version_id,
                'resolved_at' => $resolvedAt,
                'created_at' => $resolvedAt,
            ]);
            $this->movementRecorder->record($actor, 'ROUTE_PLAN_CREATED', 'ROUTE_PLAN', $id, $consignmentId, 'PLANNED', $correlationId);
            return $id;
        });
        return $this->getRoutePlan->handle(new \Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand($actor, $nodeId, $id))->data;
    }
}
