<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Consignment\Domain\ValueObjects\NumberRangePreview;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberAllocationRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRegistryRecord;

final class EloquentNumberRangeRepository implements NumberRangeRepositoryInterface
{
    /** The single registry row every range creation serializes on. */
    private const REGISTRY_KEY = 'GLOBAL';

    public function findByTenant(string $hqId, string $rangeId): ?NumberRangeRecord
    {
        return NumberRangeRecord::query()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->first();
    }

    public function existsInTenant(string $hqId, string $rangeId): bool
    {
        return NumberRangeRecord::query()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->exists();
    }

    public function lockByTenant(string $hqId, string $rangeId): ?NumberRangeRecord
    {
        return NumberRangeRecord::query()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->lockForUpdate()->first();
    }

    public function lockNextAllocatable(string $hqId): ?NumberRangeRecord
    {
        return NumberRangeRecord::query()->where(['hq_id' => $hqId, 'status' => 'AVAILABLE'])
            ->whereNotNull('next_serial')->orderBy('created_at')->orderBy('range_id')->lockForUpdate()->first();
    }

    public function overlaps(NumberRangePreview $preview): bool
    {
        return NumberRangeRecord::query()->overlapping($preview)->exists();
    }

    public function lockRegistry(DateTimeInterface $at): void
    {
        NumberRangeRegistryRecord::query()->insertOrIgnore(['registry_key' => self::REGISTRY_KEY, 'updated_at' => $at]);
        NumberRangeRegistryRecord::query()->where('registry_key', self::REGISTRY_KEY)->lockForUpdate()->firstOrFail();
    }

    public function paginate(string $hqId, ?string $status, int $page, int $pageSize): LengthAwarePaginator
    {
        return NumberRangeRecord::query()->where('hq_id', $hqId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')->orderBy('range_id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    public function paginateAllocations(string $hqId, string $rangeId, int $page, int $pageSize): LengthAwarePaginator
    {
        return NumberAllocationRecord::query()->where(['hq_id' => $hqId, 'range_id' => $rangeId])
            ->orderByDesc('allocated_at')->orderBy('allocation_id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }
}
