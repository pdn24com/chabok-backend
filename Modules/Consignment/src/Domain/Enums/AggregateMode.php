<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

/** Whether every parcel of a Consignment reached the aggregate status, or only some. */
enum AggregateMode: string
{
    public static function forCoverage(int $reached, int $total): self
    {
        return $total > 0 && $reached === $total ? self::Full : self::Partial;
    }
    case Full = 'FULL';
    case Partial = 'PARTIAL';
}
