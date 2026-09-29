<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum EligibilityOutcome: string
{
    case Eligible = 'ELIGIBLE';
    case Ineligible = 'INELIGIBLE';
    case Unknown = 'UNKNOWN';
}
