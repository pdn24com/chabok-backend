<?php

declare(strict_types=1);

namespace Modules\Organization\Domain\Enums;

enum NetworkStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
