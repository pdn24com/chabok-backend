<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use DateTimeImmutable;
use Modules\Consignment\Application\Contracts\ManifestConsignmentAccessInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestRoutePlannerInterface;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Dto\ManifestRouteReferenceDto;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Serialization\CoverageEvidence;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionHandler;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

final readonly class ManifestRoutePlanner implements ManifestRoutePlannerInterface
{
    public function __construct(
        private ManifestRouteAccessInterface $manifestRouteAccess,
        private ClockInterface $clock,
        private ManifestConsignmentAccessInterface $manifestConsignmentAccess,
        private ManifestDirectoryReaderInterface $manifestDirectoryReader,
        private ResolveCoveragePolicyHandler $resolveCoveragePolicyHandler,
        private ResolveRouteDefinitionHandler $resolveRouteDefinitionHandler,
        private AuditWriterInterface $auditWriter,
        private ManifestTransitionRecorderInterface $manifestTransitionRecorder,
    ) {}

    public function ensureRoute(
        AuthenticatedPrincipal $actor,
        string $node,
        ParcelRecord $parcel,
        string $correlationId,
    ): ManifestRouteReferenceDto {
        $plan = $this->manifestRouteAccess->lockActivePlan($actor->hqId, $parcel->consignment_id);
        if ($plan === null) {
            $plan = $this->createPlan($actor, $node, (string) $parcel->consignment_id, $correlationId);
        }
        $leg = $this->manifestRouteAccess->lockNextOriginLeg($actor->hqId, $plan->route_plan_id, $node);
        if ($leg === null) {
            throw new ApiException(ApiErrorCode::RouteLegUnavailable, 422, 'manifest.no_next_route_leg_is_available');
        }
        if ((int) $leg->leg_order > 1 && ! $this->manifestRouteAccess->previousLegReceived($plan->route_plan_id, $leg->leg_order)) {
            throw new ApiException(ApiErrorCode::RouteLegNotReady, 422, 'manifest.preceding_route_leg_is_not_received');
        }
        if ((string) $leg->status === 'PENDING') {
            $this->manifestRouteAccess->updateLeg($leg->route_plan_leg_id, [
                'status' => 'ROUTED',
                'routed_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->manifestRouteAccess->updatePlan($plan->route_plan_id, [
                'status' => 'IN_PROGRESS',
                'version' => (int) $plan->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
        }

        return new ManifestRouteReferenceDto(
            $plan->route_plan_id,
            $plan->route_definition_version_id,
            $leg->route_plan_leg_id,
            $leg->source_route_definition_version_leg_id,
        );
    }

    public function createPlan(
        AuthenticatedPrincipal $actor,
        string $node,
        string $consignmentId,
        string $correlationId,
    ): RoutePlanRecord {
        $consignment = $this->manifestConsignmentAccess->lockAtPickupNode($actor->hqId, $consignmentId, $node);
        if ($consignment === null) {
            throw new ApiException(ApiErrorCode::RoutePlanUnavailable, 422, 'manifest.consignment_cannot_resolve_route_plan_node');
        }
        $location = CoverageLocation::fromCoordinates(
            provinceId: $consignment->receiver_city_id !== null ? $this->manifestDirectoryReader->provinceForActiveCity($consignment->receiver_city_id) : null,
            cityId: $consignment->receiver_city_id,
            postalCode: preg_match('/^\d{10}$/', (string) $consignment->receiver_postal_code) === 1 ? $consignment->receiver_postal_code : null,
            latitude: $consignment->receiver_latitude !== null ? (float) $consignment->receiver_latitude : null,
            longitude: $consignment->receiver_longitude !== null ? (float) $consignment->receiver_longitude : null,
        );
        if ($location->cityId === null && $location->postalCode === null && $location->point === null) {
            throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'manifest.canonical_destination_geography_is_unavailable');
        }
        $at = $this->clock->now();
        $coverage = $this->resolveCoveragePolicyHandler->handle(new ResolveCoveragePolicyCommand((string) $actor->hqId, CoverageTarget::DestinationGateway, $location, $consignment->service_offering_version_id, $at));
        $route = $this->resolveRouteDefinitionHandler->handle(new ResolveRouteDefinitionCommand((string) $actor->hqId, RoutePurpose::Trunk, $node, (string) $coverage->rule->target_node_id, $consignment->service_offering_version_id, $at));
        $legs = $route->legs;
        if ($legs->isEmpty()) {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'manifest.published_route_version_is_unavailable');
        }
        $plan = $this->manifestRouteAccess->insertPlan([

            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
            'route_definition_id' => $route->route_definition_id,
            'route_definition_version_id' => $route->route_definition_version_id,
            'status' => 'PLANNED',
            'active_slot' => hash('sha256', $actor->hqId.'|'.$consignmentId.'|ACTIVE'),
            'version' => 1,
            'created_by' => $actor->userId,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $id = (string) $plan->getKey();
        $ordered = $this->insertPlanLegs($actor, $plan, $route, $at);
        $geographyEvidence = CoverageEvidence::geography($coverage);
        $postalEvidence = CoverageEvidence::postal($coverage);
        $geometryEvidence = CoverageEvidence::geometry($coverage);
        $this->manifestRouteAccess->insertResolutionEvidence([

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
            'route_definition_id' => $route->route_definition_id,
            'route_definition_version_id' => $route->route_definition_version_id,
            'route_purpose' => 'TRUNK',
            'ordered_route_legs' => $ordered,
            'offering_version_id' => $consignment->service_offering_version_id,
            'resolved_at' => $at,
            'created_at' => $at,
        ]);
        $this->auditWriter->write($actor->hqId, $actor->userId, 'ROUTE_PLAN_CREATED', 'ROUTE_PLAN', $id, $correlationId);
        $this->manifestTransitionRecorder->commandEvent($actor, $id, $consignmentId, 'ROUTE_PLAN_CREATED', 'PLANNED', $correlationId);

        return $plan;
    }

    /** @return list<array{route_definition_version_leg_id:string, leg_order:int, origin_node_id:string, destination_node_id:string}> Frozen route evidence. */
    private function insertPlanLegs(AuthenticatedPrincipal $actor, RoutePlanRecord $plan, RouteDefinitionVersionRecord $route, DateTimeImmutable $at): array
    {
        $id = $plan->route_plan_id;
        $legs = $route->legs;
        $ordered = [];
        $planLegs = [];
        $legacyLegs = $this->manifestRouteAccess->legacyLegsByOrder($actor->hqId, $route->route_definition_id);
        foreach ($legs as $leg) {
            $legacy = $legacyLegs->get($leg->leg_order);
            if ($legacy === null) {
                throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'manifest.published_route_leg_evidence_is_unavailable');
            }
            $planLegs[] = [

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
            ];
            $ordered[] = [
                'route_definition_version_leg_id' => (string) $leg->route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'origin_node_id' => (string) $leg->origin_node_id,
                'destination_node_id' => (string) $leg->destination_node_id,
            ];
        }
        $this->manifestRouteAccess->insertPlanLegs($planLegs);

        return $ordered;
    }
}
