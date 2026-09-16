<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

final readonly class NumberRangeOverlap
{
    public function __construct(private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges)
    {
    }

    public function overlaps(array $preview): bool
    {
        return $this->ranges->overlaps($preview);
    }
}
