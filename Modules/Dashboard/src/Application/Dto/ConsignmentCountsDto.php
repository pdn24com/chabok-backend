<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

final readonly class ConsignmentCountsDto
{
    /** @param array<string, int> $statusCounts */
    public function __construct(public int $total, public int $active, public array $statusCounts) {}
}
