<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Modules\ServiceCatalog\Application\Repositories\CatalogIdentityRepository;
use Modules\ServiceCatalog\Infrastructure\Persistence\CatalogStorageMap;

final class EloquentCatalogIdentityRepository implements CatalogIdentityRepository
{
    public function current(string $resource, string $reference, ?string $hqId, bool $locking): ?object
    {
        [$identities, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        $stableId = CatalogStorageMap::query($versions)->where($versionId, $reference)->value($identityId) ?? $reference;
        return CatalogStorageMap::query($versions)->from("{$versions} as v")->join("{$identities} as i", "i.{$identityId}", '=', "v.{$identityId}")->where("i.{$identityId}", $stableId)->where('i.status', 'ACTIVE')->where('v.status', 'PUBLISHED')->when($hqId !== null, fn($q) => $q->where(fn($scope) => $scope->whereNull('i.hq_id')->orWhere('i.hq_id', $hqId)))->orderByDesc('v.version_number')->when($locking, fn($q) => $q->lockForUpdate())->select(['v.*', 'i.code'])->first();
    }

    public function relatedVersions(string $resource, string $reference): array
    {
        [, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        $id = CatalogStorageMap::query($versions)->where($versionId, $reference)->value($identityId) ?? $reference;
        return CatalogStorageMap::query($versions)->where($identityId, $id)->pluck($versionId)->all();
    }

    public function optionBound(string $offeringVersionId, array $optionVersionIds): bool
    {
        return CatalogStorageMap::query('service_offering_option_rules')->where('service_offering_version_id', $offeringVersionId)->whereIn('service_option_version_id', $optionVersionIds)->exists();
    }

    public function lockIdentityForRevision(string $resource, string $reference): void
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        $id = CatalogStorageMap::query($versions)->where($versionId, $reference)->value($identityId);
        CatalogStorageMap::query($table)->where($identityId, $id)->lockForUpdate()->first();
    }

    public function revisionKey(string $resource): string
    {
        return CatalogStorageMap::MAP[$resource][3];
    }
}
