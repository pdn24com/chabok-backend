<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

/** Status-group counts aggregated in SQL, so a badge row never depends on the page size. */
final readonly class ConsignmentStatusGroupTotalsDto
{
    public function __construct(
        public int $total = 0,
        public int $newRouted = 0,
        public int $unassigned = 0,
        public int $assigned = 0,
        public int $inOperation = 0,
        public int $exception = 0,
        public int $completed = 0,
        public int $cancelled = 0,
    ) {}
}
