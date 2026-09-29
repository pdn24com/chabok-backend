<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum InvitationStatus: string
{
    case PENDING = 'PENDING';
    case SUPERSEDED = 'SUPERSEDED';
    case ACCEPTED = 'ACCEPTED';
    case EXPIRED = 'EXPIRED';
}
