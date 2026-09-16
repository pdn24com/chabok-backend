<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class LastMileResolver
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Operations\Application\CoveragePolicyService $coverage,
        private \Modules\Operations\Application\Services\DeliveryNodeCapabilities $deliveryNodeCapabilities,
        private \Modules\Operations\Application\RouteDefinitionService $routes,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Foundation\Application\Contracts\CorrelationIdProvider $correlation,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function resolveLastMile(AuthenticatedPrincipal $actor, string $gatewayNodeId, object $consignment): void
    {
        $city = $consignment->receiver_city_id === null ? null : $this->tasks->city($consignment->receiver_city_id);
        $input = array_filter([
            'province_id' => $city?->province_id,
            'city_id' => $consignment->receiver_city_id,
            'postal_code' => $consignment->receiver_postal_code,
            'latitude' => $consignment->receiver_latitude === null ? null : (float) $consignment->receiver_latitude,
            'longitude' => $consignment->receiver_longitude === null ? null : (float) $consignment->receiver_longitude,
        ], fn($value) => $value !== null && $value !== '');
        $coverage = $this->coverage->resolve((string) $actor->hqId, 'LAST_MILE_NODE', $input, $consignment->service_offering_version_id);
        $lastMileNodeId = (string) $coverage['target_node_id'];
        if (!$this->deliveryNodeCapabilities->nodeHasCapability((string) $actor->hqId, $lastMileNodeId, 'DELIVERY')) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published coverage resolved a Node without DELIVERY capability.');
        }
        $planId = (string) $this->tasks->activeRoutePlanId($actor->hqId, $consignment->consignment_id);
        if ($planId === '') {
            throw new ApiException(ApiErrorCode::ConfigVersionUnavailable, 422, 'An active Route Plan is required for Last-mile resolution.');
        }
        $route = $lastMileNodeId === $gatewayNodeId ? null : $this->routes->resolve((string) $actor->hqId, 'LAST_MILE', $gatewayNodeId, $lastMileNodeId, $consignment->service_offering_version_id);
        $resolutionId = $this->identifiers->uuid();
        $this->tasks->insertResolution([
            'last_mile_resolution_id' => $resolutionId,
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignment->consignment_id,
            'route_plan_id' => $planId,
            'destination_gateway_node_id' => $gatewayNodeId,
            'last_mile_node_id' => $lastMileNodeId,
            'coverage_policy_id' => $coverage['coverage_policy_id'],
            'coverage_policy_version_id' => $coverage['coverage_policy_version_id'],
            'coverage_rule_id' => $coverage['coverage_rule_id'],
            'route_definition_version_id' => $route['route_definition_version_id'] ?? null,
            'resolution_input' => json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'resolved_by' => $actor->userId,
            'resolved_at' => CarbonImmutable::parse($coverage['resolved_at'])->utc()->format('Y-m-d H:i:s.u'),
        ]);
        if ($route !== null) {
            $nextOrder = (int) $this->tasks->lastLegOrder($planId) + 1;
            foreach ($route['legs'] as $index => $leg) {
                $this->tasks->insertRouteLeg([
                    'route_plan_leg_id' => $this->identifiers->uuid(),
                    'hq_id' => $actor->hqId,
                    'route_plan_id' => $planId,
                    'source_route_definition_leg_id' => $leg['route_definition_leg_id'],
                    'source_route_definition_version_leg_id' => $leg['route_definition_leg_id'],
                    'leg_order' => $nextOrder + $index,
                    'origin_node_id' => $leg['origin_node_id'],
                    'destination_node_id' => $leg['destination_node_id'],
                    'status' => 'PENDING',
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            }
            $this->tasks->startRoutePlan($planId, $this->clock->now());
        }
        $this->ledger->setDeliveryNode($consignment->consignment_id, $lastMileNodeId, $this->clock->now());
        $correlationId = $this->correlation->current();
        $this->audit->write($actor->hqId, $actor->userId, 'LAST_MILE_NODE_RESOLVED', 'CONSIGNMENT', (string) $consignment->consignment_id, $correlationId, after: [
            'last_mile_resolution_id' => $resolutionId,
            'destination_gateway_node_id' => $gatewayNodeId,
            'last_mile_node_id' => $lastMileNodeId,
        ], sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'CONSIGNMENT', (string) $consignment->consignment_id, 'operations.command.executed', $correlationId, [
            'command' => 'LAST_MILE_NODE_RESOLVED',
            'resource_id' => (string) $consignment->consignment_id,
            'consignment_id' => (string) $consignment->consignment_id,
            'status' => 'RESOLVED',
        ]);
    }
}
