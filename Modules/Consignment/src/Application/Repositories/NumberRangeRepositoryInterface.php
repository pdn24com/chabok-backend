<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Domain\ValueObjects\NumberRangePreview;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberAllocationRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;

interface NumberRangeRepositoryInterface
{
    public function findByTenant(string $hqId, string $rangeId): ?NumberRangeRecord;

    public function existsInTenant(string $hqId, string $rangeId): bool;

    public function lockByTenant(string $hqId, string $rangeId): ?NumberRangeRecord;

    /** The oldest available range that still has serials left, locked for allocation. */
    public function lockNextAllocatable(string $hqId): ?NumberRangeRecord;

    public function overlaps(NumberRangePreview $preview): bool;

    /** Serializes range creation across tenants, because ranges are a global namespace. */
    public function lockRegistry(DateTimeInterface $at): void;

    /** @return LengthAwarePaginator<NumberRangeRecord> */
    public function paginate(string $hqId, ?string $status, int $page, int $pageSize): LengthAwarePaginator;

    /** @return LengthAwarePaginator<NumberAllocationRecord> */
    public function paginateAllocations(string $hqId, string $rangeId, int $page, int $pageSize): LengthAwarePaginator;
}
