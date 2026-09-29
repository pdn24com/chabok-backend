<?php

declare(strict_types=1);

namespace Modules\Authorization\Domain\Enums;

enum AssignmentScopeFailure
{
    case TenantTarget;
    case SelfTarget;
    case AreaTarget;
    case NodeTarget;
    case UnsupportedTarget;
    case InvalidType;
}
