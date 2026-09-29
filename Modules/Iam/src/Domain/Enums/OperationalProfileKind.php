<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum OperationalProfileKind: string
{
    case Driver = 'DRIVER';
    case Node = 'NODE';
}
