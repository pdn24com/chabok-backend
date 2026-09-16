<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Data\Page;
use Modules\Consignment\Application\Repositories\NumberRangeRepository;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberAllocationRecord;

final class EloquentNumberRangeRepository implements NumberRangeRepository
{
    public function paginateRanges(string $hqId, array $filters): Page
    {
        $query = NumberRangeRecord::query()->toBase()->where('hq_id', $hqId);
        if (($filters['status'] ?? null) !== null) {
            $query->where('status', $filters['status']);
        }
        $page = $query->orderByDesc('created_at')->orderBy('range_id')->paginate((int) ($filters['page_size'] ?? 25), ['*'], 'page', (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function paginateAllocations(string $hqId, string $rangeId, array $filters): Page
    {
        $page = NumberAllocationRecord::query()->toBase()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->orderByDesc('allocated_at')->orderBy('allocation_id')->paginate((int) ($filters['page_size'] ?? 25), ['*'], 'page', (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function find(string $hqId, string $rangeId): ?object
    {
        return NumberRangeRecord::query()->toBase()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->first();
    }

    public function lock(string $hqId, string $rangeId): ?object
    {
        return NumberRangeRecord::query()->toBase()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->lockForUpdate()->first();
    }

    public function exists(string $hqId, string $rangeId): bool
    {
        return NumberRangeRecord::query()->toBase()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->exists();
    }

    public function lockRegistry(): ?object
    {
        return DB::table('consignment_number_range_registry')->where('registry_key', 'GLOBAL')->lockForUpdate()->first();
    }

    public function overlaps(array $preview): bool
    {
        return NumberRangeRecord::query()->toBase()->where('total_length', $preview['total_length'])->where('first_number', '<=', $preview['last_number'])->where('last_number', '>=', $preview['first_number'])->exists();
    }

    public function insert(array $attributes): void
    {
        NumberRangeRecord::query()->toBase()->insert($attributes);
    }

    public function update(string $hqId, string $rangeId, array $attributes): void
    {
        NumberRangeRecord::query()->toBase()->where(['hq_id' => $hqId, 'range_id' => $rangeId])->update($attributes);
    }

    public function lockNextAvailable(string $hqId): ?object
    {
        return NumberRangeRecord::query()->toBase()->where(['hq_id' => $hqId, 'status' => 'AVAILABLE'])->whereNotNull('next_serial')->orderBy('created_at')->orderBy('range_id')->lockForUpdate()->first();
    }

    public function advanceAvailable(string $hqId, string $rangeId, array $attributes): void
    {
        NumberRangeRecord::query()->toBase()->where(['hq_id' => $hqId, 'range_id' => $rangeId, 'status' => 'AVAILABLE'])->update($attributes);
    }

    public function appendAllocation(array $attributes): void
    {
        NumberAllocationRecord::query()->toBase()->insert($attributes);
    }
}
