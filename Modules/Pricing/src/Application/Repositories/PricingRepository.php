<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface PricingRepository
{
    public function listTariffs(string $hqId, array $filters): Page;

    public function listZoneSets(string $hqId, array $filters): Page;

    public function listZoneSetVersionReferences(string $hqId, array $filters): Page;

    public function auditEvents(string $hqId, array $filters): Page;

    public function serviceTariffReferences(string $hqId, \DateTimeInterface $at): array;

    public function chargeTypes(): array;

    public function identityVisible(string $hqId, string $kind, string $identityId): bool;

    public function history(string $kind, string $identityId): array;

    public function tenantIdentityExists(string $hqId, string $kind, string $identityId): bool;

    public function lockLatestVersion(string $kind, string $identityId): ?object;

    public function hasUnpublishedSuccessor(string $kind, string $identityId): bool;

    public function insertVersion(string $kind, array $attributes): void;

    public function unorderedRules(string $versionId): array;

    public function chargeType(string $id): ?object;

    public function lockZoneVersion(string $hqId, string $versionId): ?object;

    public function lockTariffVersion(string $hqId, string $versionId): ?object;

    public function updateZoneVersion(string $versionId, array $changes): void;

    public function updateTariffVersion(string $versionId, array $changes): void;

    public function offeringHasPublishedSuccessor(string $reference): bool;

    public function lockTenant(string $hqId): void;

    public function lockVersion(string $hqId, string $kind, string $versionId): ?object;

    public function updateVersion(string $kind, string $versionId, array $changes): void;

    public function lockSimulationTariff(string $hqId, string $versionId): ?object;

    public function quoteForRequest(string $hqId, string $userId, string $idempotencyKey): ?object;

    public function eligibleTariff(string $hqId, array $offeringReferences, \DateTimeInterface $asOf): ?object;

    public function zones(string $versionId): array;

    public function freightRules(string $tariffId, array $offeringReferences, array $optionReferences): array;

    public function serviceRules(string $tariffId, ?string $cellId): array;

    public function quote(string $hqId, string $quoteId): ?object;

    public function quoteLinesWithCategory(string $quoteId): array;

    public function rejectQuote(string $hqId, string $quoteId, \DateTimeInterface $at): void;

    public function snapshotForAcceptance(string $hqId, string $userId, string $idempotencyKey): ?object;

    public function consignmentExists(string $hqId, string $objectId): bool;

    public function lockQuote(string $hqId, string $quoteId): ?object;

    public function quoteLines(string $quoteId): array;

    public function chargeCategories(array $chargeIds): array;

    public function acceptQuote(string $quoteId, \DateTimeInterface $now): void;

    public function tariffVersion(string $hqId, string $versionId): ?object;

    public function orderedRules(string $versionId): array;

    public function zoneVersion(string $hqId, string $versionId): ?object;

    public function zoneMembers(string $zoneId): array;

    public function snapshot(string $snapshotId): ?object;

    public function chargeLines(string $snapshotId): array;

    public function savedPostalMembers(string $versionId): array;

    public function zoneIdsByCode(string $versionId): array;

    public function deleteZones(string $versionId): void;

    public function activeProvince(string $provinceId): bool;

    public function deleteRules(string $versionId): void;

    public function zoneCodesById(?string $versionId): array;

    public function zoneGroup(string $configuredVersionId): ?string;

    public function effectiveZoneVersions(string $identityId, \DateTimeInterface $asOf): array;

    public function activeCity(string $cityId): ?object;

    public function citiesByName(string $normalizedName): array;

    public function zoneVersionVisible(string $hqId, string $zoneSetVersionId): bool;

    public function zoneIds(string $zoneSetVersionId): array;

    public function hasVersionOverlap(string $kind, array $version): bool;

    public function defaultConflict(array $version, string $hqId): bool;

    public function insertChargeType(array $attributes): void;

    public function insertZoneSet(array $attributes): void;

    public function insertZoneVersion(array $attributes): void;

    public function insertTariffFamily(array $attributes): void;

    public function insertTariffVersion(array $attributes): void;

    public function insertQuote(array $attributes): void;

    public function insertSnapshot(array $attributes): void;

    public function insertChargeLine(array $attributes): void;

    public function insertZone(array $attributes): void;

    public function insertZoneMember(array $attributes): void;

    public function insertRateRule(array $attributes): void;

    public function insertQuoteLine(array $attributes): void;
}
