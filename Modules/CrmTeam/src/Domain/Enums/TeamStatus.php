<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Domain\Enums;

enum TeamStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
