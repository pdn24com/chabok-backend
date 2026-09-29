<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentCalculation: string
{
    case Elapsed = 'ELAPSED';
    case DayEnd = 'DAY_END';
    case BusinessDayEnd = 'BUSINESS_DAY_END';
}
