<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

enum OperationalStatusScope: string
{
    case Global = 'GLOBAL';
    case Tenant = 'TENANT';
}
