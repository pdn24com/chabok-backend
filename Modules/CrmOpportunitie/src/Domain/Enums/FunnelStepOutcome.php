<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Domain\Enums;

/** The business result of a funnel step, readable independently of the step title. */
enum FunnelStepOutcome: string
{
    case OPEN = 'OPEN';
    case WON = 'WON';
    case LOST = 'LOST';
}
