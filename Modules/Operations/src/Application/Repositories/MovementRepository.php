<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

interface MovementRepository
{
    public function activePlan(string $hqId, string $consignmentId): ?object;

    public function sourceLegs(string $hqId, string $versionId): array;

    public function legacyLegId(string $hqId, int $legOrder, string $definitionId): ?string;

    public function lockActivePlan(string $hqId, string $consignmentId): ?object;

    public function lockPendingLeg(string $hqId, string $planId, string $nodeId): ?object;

    public function previousLegReceived(string $planId, int $previousOrder): bool;

    public function activePlanId(string $hqId, string $consignmentId): ?string;

    public function plan(string $hqId, string $id): ?object;

    public function plansVisibleAtNode(string $hqId, string $nodeId): array;

    public function provinceForActiveCity(string $cityId): ?string;

    public function configurationEvidence(string $hqId, string $planId, ?string $versionId): ?object;

    public function configurationVersion(string $hqId, string $definitionId, ?string $versionId): ?object;

    public function coverageVersionStatus(string $hqId, string $versionId): ?string;

    public function sourceLegsAvailable(string $planId): bool;

    public function consignmentOriginatesAt(string $consignmentId, string $nodeId): bool;

    public function planTouchesNode(string $planId, string $nodeId): bool;

    public function consignment(string $hqId, string $consignmentId): ?object;

    public function definition(string $hqId, string $definitionId): ?object;

    public function routeVersionStatus(string $hqId, ?string $versionId): ?string;

    public function resolutionEvidence(string $hqId, string $planId): ?object;

    public function planLegs(string $planId): array;

    public function insertPlan(array $attributes): void;

    public function insertLeg(array $attributes): void;

    public function insertEvidence(array $attributes): void;

    public function updateLeg(string $id, array $changes): void;

    public function updatePlan(string $id, array $changes): void;
}
