<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Enums;

enum OtpStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case CONSUMED = 'CONSUMED';
    case EXPIRED = 'EXPIRED';
    case LOCKED = 'LOCKED';
}
