<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Domain\Enums;

/** A membership is current or it has ended; ending one keeps the row and its history. */
enum MembershipStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ENDED = 'ENDED';
}
