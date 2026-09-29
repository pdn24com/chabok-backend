<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */
interface ManifestRouteAccessInterface
{
    public function hasUnreceivedLeg(?string $hqId, ?string $activeRoutePlanId): bool;

    public function plansByIds(array $referenceIds, string $hq): Collection;

    public function legsByIds(array $referenceIds, string $hq): Collection;

    public function outboundLegContexts(string $hq, string $node): Collection;

    public function planSummary(string $hq, ?string $id): ?RoutePlanRecord;

    public function leg(string $hq, ?string $id): ?RoutePlanLegRecord;

    public function lockDepartureLeg(?string $hqId, ?string $activeRoutePlanLegId): ?RoutePlanLegRecord;

    public function updateTenantLeg(
        ?string $hqId,
        ?string $routePlanLegId,
        array $changes,
    ): void;

    public function lockReceptionLeg(
        ?string $hqId,
        ?string $routePlanLegId,
        ?string $routePlanId,
    ): ?RoutePlanLegRecord;

    public function followingLeg(
        ?string $hqId,
        ?string $routePlanId,
        int $legOrder,
        string $node,
    ): ?RoutePlanLegRecord;

    public function updateTenantPlan(
        ?string $hqId,
        ?string $routePlanId,
        array $changes,
    ): void;

    public function confirmOutboundLeg(
        ?string $hqId,
        ?string $routePlanLegId,
        array $changes,
    ): void;

    public function lockActivePlan(?string $hqId, ?string $consignmentId): ?RoutePlanRecord;

    public function lockNextOriginLeg(
        ?string $hqId,
        ?string $routePlanId,
        string $node,
    ): ?RoutePlanLegRecord;

    public function previousLegReceived(?string $routePlanId, int $legOrder): bool;

    public function updateLeg(?string $routePlanLegId, array $changes): void;

    public function updatePlan(?string $routePlanId, array $changes): void;

    public function insertPlan(array $attributes): RoutePlanRecord;

    public function legacyLegsByOrder(
        ?string $hqId,
        ?string $routeDefinitionId,
    ): SupportCollection;

    public function insertPlanLegs(array $attributes): void;

    public function insertResolutionEvidence(array $attributes): void;

    /** Every leg of the given plans plus one explicitly named leg, keyed by leg, so eligibility needs a single read. @param list<string> $routePlanIds @return Collection<string, RoutePlanLegRecord> */
    public function legsForPlansAndLeg(?string $hqId, array $routePlanIds, ?string $routePlanLegId): Collection;
}
