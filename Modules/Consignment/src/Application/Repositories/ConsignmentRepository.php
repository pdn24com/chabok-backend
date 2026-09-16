<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface ConsignmentRepository
{
    public function list(string $hqId, string $nodeId, array $filters): Page;

    public function filterOptions(string $hqId, string $nodeId): array;

    public function statusGroupCounts(string $hqId, string $nodeId, array $filters): array;

    public function findVisible(string $hqId, array $nodeIds, string $consignmentId): ?object;

    public function lockVisible(string $hqId, array $nodeIds, string $consignmentId): ?object;

    public function activeNodeExists(string $nodeId, string $hqId): bool;

    public function lockParcels(string $hqId, string $consignmentId): array;

    public function offeringEvidence(string $offeringVersionId, string $hqId): ?object;

    public function parcelsInNumberOrder(string $hqId, string $id): array;

    public function pricingVersions(string $hqId, string $id): array;

    public function chargeLines(string $pricingVersionId, string $hqId): array;

    public function statusHistory(string $hqId, string $id): array;

    public function auditHistory(string $hqId, string $id): array;

    public function custodyHistory(string $hqId, string $id): array;

    public function latestRoutePlan(string $hqId, string $id): ?object;

    public function routeLegs(string $planId): array;

    public function relatedManifests(string $hqId, string $id): array;

    public function manifestOutcomes(array $manifestIds, string $hqId, string $id): array;

    public function parcels(string $hqId, string $consignmentId): array;

    public function node(string $hqId, string $nodeId): ?object;

    public function draftParcels(string $hqId, string $consignmentId): array;

    public function insertConsignment(array $attributes): void;

    public function insertParcel(array $attributes): void;

    public function appendCustodyEvent(array $attributes): void;

    public function insertPricingVersion(array $attributes): void;

    public function insertPricingChargeLine(array $attributes): void;

    public function appendStatusEvent(array $attributes): void;

    public function updateParcel(string $hqId, string $consignmentId, string $parcelId, array $changes): void;

    public function updateConsignmentVersion(string $hqId, string $consignmentId, int $expectedVersion, array $changes): void;

    public function acceptPricing(string $hqId, string $consignmentId, array $changes): void;

    public function nextCustodySequence(string $consignmentId): int;

    public function nextStatusSequence(string $consignmentId): int;
}
