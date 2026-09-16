<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */

interface ManifestRouteAccess
{
    public function inTransitLeg(?string $hqId, ?string $routePlanLegId, ?string $routePlanId, string $node): ?object;

    public function hasFollowingLeg(?string $hqId, ?string $routePlanId, int $legOrder, string $node): bool;

    public function outboundLegReady(?string $hqId, ?string $routePlanLegId, string $node): bool;

    public function departureLeg(
        ?string $hqId,
        ?string $activeRoutePlanLegId,
        ?string $activeRoutePlanId,
        ?string $destinationNodeId,
        ?string $routeDefinitionVersionLegId,
        string $node,
    ): ?object;

    public function hasUnreceivedLeg(?string $hqId, ?string $activeRoutePlanId): bool;

    public function plansByIds(array $referenceIds, string $hq): array;

    public function legsByIds(array $referenceIds, string $hq): array;

    public function outboundLegContexts(string $hq, string $node): array;

    public function planSummary(string $hq, ?string $id): ?object;

    public function leg(string $hq, ?string $id): ?object;

    public function lockDepartureLeg(?string $hqId, ?string $activeRoutePlanLegId): ?object;

    public function updateTenantLeg(?string $hqId, ?string $routePlanLegId, array $changes): void;

    public function lockReceptionLeg(?string $hqId, ?string $routePlanLegId, ?string $routePlanId): ?object;

    public function followingLeg(?string $hqId, ?string $routePlanId, int $legOrder, string $node): ?object;

    public function updateTenantPlan(?string $hqId, ?string $routePlanId, array $changes): void;

    public function confirmOutboundLeg(?string $hqId, ?string $routePlanLegId, array $changes): void;

    public function lockActivePlan(?string $hqId, ?string $consignmentId): ?object;

    public function lockNextOriginLeg(?string $hqId, ?string $routePlanId, string $node): ?object;

    public function previousLegReceived(?string $routePlanId, int $legOrder): bool;

    public function updateLeg(?string $routePlanLegId, array $changes): void;

    public function updatePlan(?string $routePlanId, array $changes): void;

    public function sourceLegs(?string $hqId, ?string $routeDefinitionVersionId): array;

    public function insertPlan(array $attributes): void;

    public function legacyLegId(?string $hqId, ?string $routeDefinitionId, int $legOrder): ?string;

    public function insertPlanLeg(array $attributes): void;

    public function insertResolutionEvidence(array $attributes): void;

    public function plan(?string $id): ?object;
}
