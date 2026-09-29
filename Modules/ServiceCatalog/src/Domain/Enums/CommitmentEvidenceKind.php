<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentEvidenceKind
{
    case BoundSchedule;
    case TimingPolicy;
    case LegacyDuration;
}
