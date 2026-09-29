<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum ContactPointIdentifierKind: string
{
    case PHONE = 'PHONE';
    case USERNAME = 'USERNAME';
    case EMAIL = 'EMAIL';
    case ADDRESS = 'ADDRESS';
}
