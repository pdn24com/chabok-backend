<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

use Modules\Dashboard\Domain\Enums\DashboardReason;

final readonly class DashboardCapabilityDto
{
    public bool $available;

    public function __construct(public ?DashboardReason $reason = null)
    {
        $this->available = $reason === null;
    }
}
