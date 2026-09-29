<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

final readonly class DriverCountsDto
{
    public function __construct(public int $total, public int $available, public int $onMission) {}
}
