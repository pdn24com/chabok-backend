<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum ContactPointStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
