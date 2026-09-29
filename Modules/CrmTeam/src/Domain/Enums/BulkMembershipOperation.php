<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Domain\Enums;

/** What a bulk change does to the memberships it names. */
enum BulkMembershipOperation: string
{
    public function eventType(): MembershipEventType
    {
        return match ($this) {
            self::ADD => MembershipEventType::ADD,
            self::END => MembershipEventType::END,
            self::TRANSFER => MembershipEventType::TRANSFER,
        };
    }
    case ADD = 'ADD';
    case END = 'END';
    case TRANSFER = 'TRANSFER';
}
