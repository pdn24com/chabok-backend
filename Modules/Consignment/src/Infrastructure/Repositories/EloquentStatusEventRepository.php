<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Support\Collection;
use Modules\Consignment\Application\Repositories\StatusEventRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;

final class EloquentStatusEventRepository implements StatusEventRepositoryInterface
{
    public function insert(array $rows): void
    {
        StatusEventRecord::query()->insert($rows);
    }

    public function sequenceHeads(string $hqId, array $consignmentIds): Collection
    {
        return StatusEventRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->selectRaw('consignment_id, MAX(event_sequence) AS sequence_head')->groupBy('consignment_id')->pluck('sequence_head', 'consignment_id');
    }
}
