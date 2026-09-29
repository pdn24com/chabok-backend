<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

enum ScopeType: string
{
    case SelfScope = 'SELF';
    case PLATFORM = 'PLATFORM';
    case TENANT = 'TENANT';
    case AREA = 'AREA';
    case NODE = 'NODE';
    case VENDOR = 'VENDOR';
    case VENDOR_BRANCH = 'VENDOR_BRANCH';
}
