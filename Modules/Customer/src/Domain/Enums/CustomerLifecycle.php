<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum CustomerLifecycle: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
