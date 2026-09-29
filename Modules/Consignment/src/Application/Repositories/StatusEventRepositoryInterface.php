<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

use Illuminate\Support\Collection;

interface StatusEventRepositoryInterface
{
    /** @param list<array<string, mixed>> $rows */
    public function insert(array $rows): void;

    /** The highest recorded sequence per Consignment, so the next event continues the chain. @param list<string> $consignmentIds @return Collection<string, int> */
    public function sequenceHeads(string $hqId, array $consignmentIds): Collection;
}
