<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

enum ContactPointScope: string
{
    case PERSONAL = 'PERSONAL';
    case WORK = 'WORK';
}
