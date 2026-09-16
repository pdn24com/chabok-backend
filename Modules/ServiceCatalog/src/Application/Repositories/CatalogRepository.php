<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface CatalogRepository
{
    public function listIdentities(string $hqId, string $resource, array $filters): Page;

    public function listPublishedVersions(string $hqId, string $resource, array $filters): Page;

    public function auditEvents(string $hqId, array $filters): Page;

    public function lockLatestVersion(string $resource, string $identityIdValue): ?object;

    public function hasUnpublishedSuccessor(string $resource, string $identityIdValue): bool;

    public function lockVersion(string $hqId, string $resource, string $versionIdValue): ?object;

    public function insertIdentity(string $resource, array $attributes): void;

    public function insertVersion(string $resource, array $attributes): void;

    public function updateVersion(string $resource, string $versionIdValue, array $changes): void;

    public function publishedVersionExists(string $resource, string $reference): bool;

    public function publishedScheduleExists(string $hqId, string $reference): bool;

    public function history(string $resource, string $identityIdValue): array;

    public function publishedOfferings(string $hqId): array;

    public function latestPublishedOffering(string $hqId, string $offeringId): ?object;

    public function deleteOfferingChildren(string $versionId): void;

    public function insertOptionRules(array $attributes): void;

    public function insertEligibilityRules(array $attributes): void;

    public function insertCoverageReferences(array $attributes): void;

    public function insertAvailabilityBindings(array $attributes): void;

    public function insertCommitmentBindings(array $attributes): void;

    public function activeProvince(string $value): bool;

    public function activeCity(string $value): bool;

    public function tenantArea(string $hqId, string $value): bool;

    public function visibleZoneSet(?string $hqId, string $value): bool;

    public function optionRules(string $versionId): array;

    public function eligibilityRules(string $versionId): array;

    public function coverageReferences(string $versionId): array;

    public function availabilityBindings(string $versionId): array;

    public function commitmentBindings(string $versionId): array;

    public function versionDetail(string $hqId, string $resource, string $versionIdValue): ?object;

    public function commitmentBinding(string $versionIdValue): ?object;

    public function hasEffectiveOverlap(string $resource, array $row): bool;

    public function optionIdentity(string $reference): ?string;

    public function offeringOwner(string $offeringVersionId): ?string;

    public function enabledAvailabilityBindings(string $versionId): array;

    public function identityVisible(string $hqId, string $resource, string $value): bool;
}
