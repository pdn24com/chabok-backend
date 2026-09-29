<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentDurationUnit: string
{
    case Minute = 'MINUTE';
    case Hour = 'HOUR';
    case Day = 'DAY';
}
