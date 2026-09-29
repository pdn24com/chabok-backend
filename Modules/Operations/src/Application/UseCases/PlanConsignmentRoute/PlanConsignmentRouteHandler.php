<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\PlanConsignmentRoute;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\DestinationResolutionInputInterface;
use Modules\Operations\Application\Contracts\MovementAccessGuardInterface;
use Modules\Operations\Application\Contracts\MovementRecorderInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Application\Serialization\CoverageEvidence;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanCommand;
use Modules\Operations\Application\UseCases\GetRoutePlan\GetRoutePlanHandler;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionHandler;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Domain\Enums\MovementCommand;
use Modules\Operations\Domain\Enums\MovementEntityType;
use Modules\Operations\Domain\Enums\RoutePlanLegStatus;
use Modules\Operations\Domain\Enums\RoutePlanStatus;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

final readonly class PlanConsignmentRouteHandler
{
    public function __construct(
        private MovementAccessGuardInterface $movementAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private DestinationResolutionInputInterface $destinationResolutionInput,
        private ResolveCoveragePolicyHandler $resolveCoveragePolicyHandler,
        private ResolveRouteDefinitionHandler $resolveRouteDefinitionHandler,
        private MovementRecorderInterface $movementRecorder,
        private GetRoutePlanHandler $getRoutePlanHandler,
        private ConsignmentRepositoryInterface $consignmentRepository,
        private RoutePlanRepositoryInterface $routePlanRepository,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(PlanConsignmentRouteCommand $command): RoutePlanRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $consignmentId = $command->consignmentId;
        $correlationId = $command->correlationId;
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.intervene');
        $id = $this->connection->transaction(function () use ($actor, $nodeId, $consignmentId, $correlationId): string {
            $consignment = $this->consignmentRepository->lockAtPickupNode($actor->hqId, $consignmentId, $nodeId);
            if ($consignment === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $existing = $this->routePlanRepository->findOpenPlan($actor->hqId, $consignmentId);
            if ($existing !== null) {
                return (string) $existing->route_plan_id;
            }
            $resolvedAt = $this->clock->now();
            $resolutionInput = $this->destinationResolutionInput->destinationResolutionInput($consignment);
            $coverage = $this->resolveCoveragePolicyHandler->handle(new ResolveCoveragePolicyCommand((string) $actor->hqId, CoverageTarget::DestinationGateway, $resolutionInput, $consignment->service_offering_version_id === null ? null : (string) $consignment->service_offering_version_id, $resolvedAt));
            $route = $this->resolveRouteDefinitionHandler->handle(new ResolveRouteDefinitionCommand((string) $actor->hqId, RoutePurpose::Trunk, $nodeId, (string) $coverage->rule->target_node_id, $consignment->service_offering_version_id === null ? null : (string) $consignment->service_offering_version_id, $resolvedAt));
            $legs = $route->legs;
            if ($legs->isEmpty()) {
                throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'manifest.published_route_version_is_unavailable');
            }
            $definitionId = (string) $route->route_definition_id;
            $orderedLegEvidence = $legs->map(fn ($leg): array => [
                'route_definition_version_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ])->all();
            $id = $this->routePlanRepository->createPlan([

                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'route_definition_id' => $definitionId,
                'route_definition_version_id' => $route->route_definition_version_id,
                'status' => RoutePlanStatus::Planned->value,
                'active_slot' => hash('sha256', "{$actor->hqId}|{$consignmentId}|ACTIVE"),
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ]);
            $this->insertPlanLegs($actor, $id, $route, $resolvedAt);
            $geographyEvidence = CoverageEvidence::geography($coverage);
            $postalEvidence = CoverageEvidence::postal($coverage);
            $geometryEvidence = CoverageEvidence::geometry($coverage);
            $this->routePlanRepository->insertResolutionEvidence([

                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'route_plan_id' => $id,
                'coverage_policy_id' => $coverage->rule->version->coverage_policy_id,
                'coverage_policy_version_id' => $coverage->rule->coverage_policy_version_id,
                'coverage_rule_id' => $coverage->rule->coverage_rule_id,
                'coverage_criterion_type' => $coverage->criterion->type->value,
                'coverage_priority' => $coverage->rule->priority,
                'resolution_input' => CoverageEvidence::location($coverage->location),
                'matched_geography_evidence' => $geographyEvidence,
                'matched_postal_evidence' => $postalEvidence,
                'matched_geometry_evidence' => $geometryEvidence,
                'destination_gateway_node_id' => $coverage->rule->target_node_id,
                'route_definition_id' => $definitionId,
                'route_definition_version_id' => $route->route_definition_version_id,
                'route_purpose' => RoutePurpose::Trunk->value,
                'ordered_route_legs' => $orderedLegEvidence,
                'offering_version_id' => $consignment->service_offering_version_id,
                'resolved_at' => $resolvedAt,
                'created_at' => $resolvedAt,
            ]);
            $this->movementRecorder->record($actor, MovementCommand::RoutePlanCreated, MovementEntityType::RoutePlan, $id, $consignmentId, RoutePlanStatus::Planned, $correlationId);

            return $id;
        }, attempts: 3);

        return $this->getRoutePlanHandler->handle(new GetRoutePlanCommand($actor, $nodeId, $id));
    }

    private function insertPlanLegs(AuthenticatedPrincipal $actor, string $id, RouteDefinitionVersionRecord $route, DateTimeImmutable $resolvedAt): void
    {
        $legs = $route->legs;
        $legacyLegs = $this->routeDefinitionRepository->activeDefinitionLegsByOrder($actor->hqId, $route->route_definition_id);
        $planLegs = [];
        foreach ($legs as $leg) {
            $legacyLegId = $legacyLegs->get($leg->leg_order);
            if ($legacyLegId === null) {
                throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'operations.published_route_version_leg_snapshot_is_unavailable');
            }
            $planLegs[] = [

                'hq_id' => $actor->hqId,
                'route_plan_id' => $id,
                'source_route_definition_leg_id' => $legacyLegId,
                'source_route_definition_version_leg_id' => $leg->route_definition_version_leg_id,
                'leg_order' => $leg->leg_order,
                'origin_node_id' => $leg->origin_node_id,
                'destination_node_id' => $leg->destination_node_id,
                'status' => RoutePlanLegStatus::Pending->value,
                'created_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ];
        }
        $this->routePlanRepository->insertLegs($planLegs);
    }
}
