<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Operations\Application\Contracts\ManifestDirectoryReader;

final class EloquentManifestDirectoryReader implements ManifestDirectoryReader
{
    public function nodesByIds(array $referenceIds, string $hq): array
    {
        return DB::table('nodes')->where('hq_id', $hq)->whereIn('node_id', $referenceIds)->get()->all();
    }

    public function activeNodes(string $hq, array $accessibleNodeIds): array
    {
        return DB::table('nodes')->where(['hq_id' => $hq, 'status' => 'ACTIVE'])->whereIn('node_id', $accessibleNodeIds)->orderBy('node_title')->orderBy('node_code')->get()->all();
    }

    public function node(?string $hqId, string $targetNodeId): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $targetNodeId])->first();
    }

    public function activeNode(string $hq, string $node): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hq, 'node_id' => $node, 'status' => 'ACTIVE'])->first();
    }

    public function OperationalContextNode(string $hq, ?string $id): ?object
    {
        return DB::table('nodes')->where(['hq_id' => $hq, 'node_id' => $id])->first();
    }

    public function provinceForActiveCity(?string $receiverCityId): ?string
    {
        return DB::table('cities')->where(['city_id' => $receiverCityId, 'is_active' => true])->value('province_id');
    }
}
