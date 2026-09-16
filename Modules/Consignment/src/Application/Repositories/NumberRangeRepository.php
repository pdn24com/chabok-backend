<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface NumberRangeRepository
{
    public function paginateRanges(string $hqId, array $filters): Page;

    public function paginateAllocations(string $hqId, string $rangeId, array $filters): Page;

    public function find(string $hqId, string $rangeId): ?object;

    public function lock(string $hqId, string $rangeId): ?object;

    public function exists(string $hqId, string $rangeId): bool;

    public function lockRegistry(): ?object;

    public function overlaps(array $preview): bool;

    public function insert(array $attributes): void;

    public function update(string $hqId, string $rangeId, array $attributes): void;

    public function lockNextAvailable(string $hqId): ?object;

    public function advanceAvailable(string $hqId, string $rangeId, array $attributes): void;

    public function appendAllocation(array $attributes): void;
}
