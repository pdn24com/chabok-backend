<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum OperationalProfileMode: string
{
    case Create = 'CREATE';
    case Link = 'LINK';
}
