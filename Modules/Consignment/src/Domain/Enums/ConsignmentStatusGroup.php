<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

enum ConsignmentStatusGroup: string
{
    case NewRouted = 'NEW_ROUTED';
    case Unassigned = 'UNASSIGNED';
    case Assigned = 'ASSIGNED';
    case InOperation = 'IN_OPERATION';
    case Exception = 'EXCEPTION';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
