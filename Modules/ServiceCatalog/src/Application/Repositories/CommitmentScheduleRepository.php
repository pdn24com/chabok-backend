<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface CommitmentScheduleRepository
{
    public function list(string $hqId, array $filters): Page;

    public function latestVersionId(string $id): ?string;

    public function published(string $hqId, array $includeVersionIds): array;

    public function identityExists(string $hqId, string $identityId): bool;

    public function hasUnpublishedSuccessor(string $identityId): bool;

    public function lockLatestVersion(string $identityId): ?object;

    public function windows(string $versionId): array;

    public function scopes(string $versionId): array;

    public function lockVersion(string $hqId, string $versionId): ?object;

    public function rename(string $hqId, string $identityId, string $title, \DateTimeInterface $at): void;

    public function overlaps(string $versionId, array $version): bool;

    public function history(string $identityId): array;

    public function pickupVersions(string $hqId, string $nodeId): array;

    public function version(string $versionId): ?object;

    public function pickupWindows(string $versionId): array;

    public function offeringBinding(string $offeringVersionId): ?object;

    public function offeringOwner(string $offeringVersionId): ?string;

    public function deliveryWindows(string $versionId): array;

    public function detail(string $hqId, string $versionId): ?object;

    public function legacyBindings(string $identityId): array;

    public function orderedWindows(string $versionId): array;

    public function deleteWindows(string $versionId): void;

    public function deleteScopes(string $versionId): void;

    public function activeWindow(string $versionId, string $type, string $code): ?object;

    public function insertIdentity(array $attributes): void;

    public function insertVersion(array $attributes): void;

    public function insertWindow(array $attributes): void;

    public function insertScope(array $attributes): void;

    public function updateVersion(string $versionId, array $changes): void;
}
