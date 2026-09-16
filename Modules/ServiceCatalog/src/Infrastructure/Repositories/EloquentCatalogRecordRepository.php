<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\ServiceCatalog\Application\Repositories\CatalogRecordRepository;
use Modules\ServiceCatalog\Infrastructure\Persistence\CatalogStorageMap;

final class EloquentCatalogRecordRepository implements CatalogRecordRepository
{
    public function identity(string $hqId, string $resource, string $id): ?object
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($table)->where([$identityId => $id, 'hq_id' => $hqId])->first();
    }

    public function lockIdentity(string $hqId, string $resource, string $id): ?object
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($table)->where([$identityId => $id, 'hq_id' => $hqId])->lockForUpdate()->first();
    }

    public function latestRevisionId(string $resource, string $id): ?string
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($identityId, $id)->orderByDesc('version_number')->value($versionId);
    }

    public function identityForRevision(string $resource, string $reference): ?string
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($versionId, $reference)->value($identityId);
    }

    public function activeNode(string $hqId, string $nodeId): bool
    {
        return DB::table('nodes')->where(['node_id' => $nodeId, 'hq_id' => $hqId, 'status' => 'ACTIVE'])->exists();
    }

    public function latestRevision(string $resource, string $id): ?object
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        return CatalogStorageMap::query($versions)->where($identityId, $id)->orderByDesc('version_number')->first();
    }

    public function insertRevision(string $resource, array $attributes): void
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($versions)->insert($attributes);
    }

    public function supersedePublished(string $resource, string $id, \DateTimeInterface $at): void
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($versions)->where($identityId, $id)->where('status', 'PUBLISHED')->update(['status' => 'SUPERSEDED', 'updated_at' => $at]);
    }

    public function updateRevision(string $resource, string $id, array $changes): void
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($versions)->where($versionId, $id)->update($changes);
    }

    public function updateIdentity(string $resource, string $id, array $changes): void
    {
        [$table, $versions, $identityId, $versionId] = CatalogStorageMap::MAP[$resource];
        CatalogStorageMap::query($table)->where($identityId, $id)->update(['edit_lock' => DB::raw('edit_lock + 1'), ...$changes]);
    }
}
