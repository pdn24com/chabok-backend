<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum TariffScope: string
{
    case Platform = 'PLATFORM';
    case Tenant = 'TENANT';
    case Segment = 'SEGMENT';
    case Customer = 'CUSTOMER';
    case Contract = 'CONTRACT';
}
