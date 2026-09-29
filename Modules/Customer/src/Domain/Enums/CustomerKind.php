<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum CustomerKind: string
{
    case PERSON = 'PERSON';
    case COMPANY = 'COMPANY';
}
