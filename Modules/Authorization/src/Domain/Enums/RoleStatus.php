<?php

declare(strict_types=1);

namespace Modules\Authorization\Domain\Enums;

enum RoleStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
