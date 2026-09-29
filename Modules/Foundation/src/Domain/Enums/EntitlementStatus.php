<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

enum EntitlementStatus: string
{
    case ENABLED = 'ENABLED';
    case SUSPENDED = 'SUSPENDED';
    case DISABLED = 'DISABLED';
}
