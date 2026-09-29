<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Domain\Enums;

enum MembershipEventType: string
{
    case ADD = 'ADD';
    case END = 'END';
    case TRANSFER = 'TRANSFER';
}
