<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

/**
 * Review state of an operational exception case.
 *
 * Shared kernel: raised by Manifest transitions and read back by Operations,
 * which may not depend on Manifest directly.
 */
enum ExceptionCaseStatus: string
{
    public function isDecided(): bool
    {
        return $this !== self::Pending;
    }
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';
}
