<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum UserStatus: string
{
    case Invited = 'INVITED';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Deactivated = 'DEACTIVATED';
}
