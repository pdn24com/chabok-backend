<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\CorrelationIdProviderInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Operations\Application\Contracts\DeliveryNodeCapabilitiesInterface;
use Modules\Operations\Application\Contracts\LastMileResolverInterface;
use Modules\Operations\Application\Mappers\CoverageInput;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionHandler;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Domain\Enums\RoutePlanStatus;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Infrastructure\Persistence\Models\LastMileResolutionRecord;

final readonly class LastMileResolver implements LastMileResolverInterface
{
    public function __construct(
        private ResolveCoveragePolicyHandler $resolveCoveragePolicyHandler,
        private DeliveryNodeCapabilitiesInterface $deliveryNodeCapabilities,
        private ResolveRouteDefinitionHandler $resolveRouteDefinitionHandler,
        private ClockInterface $clock,
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private CorrelationIdProviderInterface $correlationIdProvider,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private CityRepositoryInterface $cityRepository,
        private ParcelRepositoryInterface $parcelRepository,
        private RoutePlanRepositoryInterface $routePlanRepository,
    ) {}

    public function resolveLastMile(
        AuthenticatedPrincipal $actor,
        string $gatewayNodeId,
        object $consignment,
    ): void {
        $city = $consignment->receiver_city_id === null ? null : $this->cityRepository->find((string) $consignment->receiver_city_id);
        $input = array_filter([
            'province_id' => $city?->province_id,
            'city_id' => $consignment->receiver_city_id,
            'postal_code' => $consignment->receiver_postal_code,
            'latitude' => $consignment->receiver_latitude === null ? null : (float) $consignment->receiver_latitude,
            'longitude' => $consignment->receiver_longitude === null ? null : (float) $consignment->receiver_longitude,
        ], fn ($value) => $value !== null && $value !== '');
        $coverage = $this->resolveCoveragePolicyHandler->handle(new ResolveCoveragePolicyCommand((string) $actor->hqId, CoverageTarget::LastMileNode, CoverageInput::location($input), $consignment->service_offering_version_id));
        $lastMileNodeId = (string) $coverage->rule->target_node_id;
        if (! $this->deliveryNodeCapabilities->nodeHasCapability((string) $actor->hqId, $lastMileNodeId, 'DELIVERY')) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.published_coverage_resolved_node_without_delivery_capability');
        }
        $planId = (string) $this->parcelRepository->activeRoutePlanId((string) $actor->hqId, (string) $consignment->consignment_id);
        if ($planId === '') {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'operations.active_route_plan_is_required_last_mile');
        }
        $route = $lastMileNodeId === $gatewayNodeId ? null : $this->resolveRouteDefinitionHandler->handle(new ResolveRouteDefinitionCommand((string) $actor->hqId, RoutePurpose::LastMile, $gatewayNodeId, $lastMileNodeId, $consignment->service_offering_version_id));
        $resolutionId = (string) LastMileResolutionRecord::query()->forceCreate([

            'hq_id' => $actor->hqId,
            'consignment_id' => $consignment->consignment_id,
            'route_plan_id' => $planId,
            'destination_gateway_node_id' => $gatewayNodeId,
            'last_mile_node_id' => $lastMileNodeId,
            'coverage_policy_id' => $coverage->rule->version->coverage_policy_id,
            'coverage_policy_version_id' => $coverage->rule->coverage_policy_version_id,
            'coverage_rule_id' => $coverage->rule->coverage_rule_id,
            'route_definition_version_id' => $route?->route_definition_version_id,
            'resolution_input' => $input,
            'resolved_by' => $actor->userId,
            'resolved_at' => $coverage->resolvedAt->format('Y-m-d H:i:s.u'),
        ])->getKey();
        if ($route !== null) {
            $nextOrder = $this->routePlanRepository->nextLegOrder($planId);
            $rows = [];
            foreach ($route->legs as $index => $leg) {
                $rows[] = [

                    'hq_id' => $actor->hqId,
                    'route_plan_id' => $planId,
                    'source_route_definition_leg_id' => $leg->route_definition_version_leg_id,
                    'source_route_definition_version_leg_id' => $leg->route_definition_version_leg_id,
                    'leg_order' => $nextOrder + $index,
                    'origin_node_id' => $leg->origin_node_id,
                    'destination_node_id' => $leg->destination_node_id,
                    'status' => 'PENDING',
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ];
            }
            $this->routePlanRepository->insertLegs($rows);
            $this->routePlanRepository->revisePlan($planId, ['status' => RoutePlanStatus::InProgress->value, 'updated_at' => $this->clock->now()]);
        }
        $this->consignmentLedgerAccess->setDeliveryNode($consignment->consignment_id, $lastMileNodeId, $this->clock->now());
        $correlationId = $this->correlationIdProvider->current();
        $this->auditWriter->write($actor->hqId, $actor->userId, 'LAST_MILE_NODE_RESOLVED', 'CONSIGNMENT', (string) $consignment->consignment_id, $correlationId, after: [
            'last_mile_resolution_id' => $resolutionId,
            'destination_gateway_node_id' => $gatewayNodeId,
            'last_mile_node_id' => $lastMileNodeId,
        ], sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'CONSIGNMENT', (string) $consignment->consignment_id, 'operations.command.executed', $correlationId, [
            'command' => 'LAST_MILE_NODE_RESOLVED',
            'resource_id' => (string) $consignment->consignment_id,
            'consignment_id' => (string) $consignment->consignment_id,
            'status' => 'RESOLVED',
        ]);
    }
}
