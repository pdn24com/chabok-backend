<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class EloquentManifestDirectoryReader implements ManifestDirectoryReaderInterface
{
    public function nodesByIds(array $referenceIds, string $hq): Collection
    {
        return NodeRecord::query()
            ->where('hq_id', $hq)
            ->whereIn('node_id', $referenceIds)
            ->get();
    }

    public function activeNodes(string $hq, array $accessibleNodeIds): Collection
    {
        return NodeRecord::query()
            ->where(['hq_id' => $hq, 'status' => 'ACTIVE'])
            ->whereIn('node_id', $accessibleNodeIds)
            ->orderBy('node_title')
            ->orderBy('node_code')
            ->get();
    }

    public function node(?string $hqId, string $targetNodeId): ?NodeRecord
    {
        return NodeRecord::query()->where(['hq_id' => $hqId, 'node_id' => $targetNodeId])->first();
    }

    public function activeNode(string $hq, string $node): ?NodeRecord
    {
        return NodeRecord::query()->where([
            'hq_id' => $hq,
            'node_id' => $node,
            'status' => 'ACTIVE',
        ])->first();
    }

    public function operationalContextNode(string $hq, ?string $id): ?NodeRecord
    {
        return NodeRecord::query()->where(['hq_id' => $hq, 'node_id' => $id])->first();
    }

    public function provinceForActiveCity(?string $receiverCityId): ?string
    {
        return CityRecord::query()->where(['city_id' => $receiverCityId, 'is_active' => true])->value('province_id');
    }
}
