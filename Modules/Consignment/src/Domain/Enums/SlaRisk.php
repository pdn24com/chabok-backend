<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

enum SlaRisk: string
{
    case Overdue = 'OVERDUE';
    case AtRisk = 'AT_RISK';
    case OnTime = 'ON_TIME';
    case NoCommitment = 'NO_COMMITMENT';
}
