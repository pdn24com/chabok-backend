<?php

declare(strict_types=1);

namespace Modules\Dashboard\Domain\Enums;

enum DashboardReason: string
{
    case EntitlementDisabled = 'ENTITLEMENT_DISABLED';
    case PermissionDenied = 'PERMISSION_DENIED';
    case DataNotPersisted = 'DATA_NOT_PERSISTED';
    case ModuleNotImplemented = 'MODULE_NOT_IMPLEMENTED';
    case SectionPermissionFiltered = 'SECTION_PERMISSION_FILTERED';
    case NoVisibleAuditTargets = 'NO_VISIBLE_AUDIT_TARGETS';
    case OperationalDateUnsupported = 'OPERATIONAL_DATE_FILTER_UNSUPPORTED';
    case ShiftUnsupported = 'SHIFT_FILTER_UNSUPPORTED';
}
