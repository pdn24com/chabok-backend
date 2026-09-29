<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum TaskAssignmentEventType: string
{
    /** Handing work to somebody else is explained; taking it yourself speaks for itself. */
    public function requiresReason(): bool
    {
        return $this === self::REFER || $this === self::REASSIGN || $this === self::MEMBERSHIP_CHANGE;
    }
    case CREATE = 'CREATE';
    case REFER = 'REFER';
    case CLAIM = 'CLAIM';
    case REASSIGN = 'REASSIGN';
    case MEMBERSHIP_CHANGE = 'MEMBERSHIP_CHANGE';
}
