<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Modules\ServiceCatalog\Domain\Enums\CommitmentAnchor;
use Modules\ServiceCatalog\Domain\Enums\CommitmentCalculation;
use Modules\ServiceCatalog\Domain\Enums\CommitmentDurationUnit;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;

final readonly class CommitmentTimingPolicy
{
    /** @param list<string> $windowCodes */
    public function __construct(
        public CommitmentMode $mode,
        public CommitmentAnchor $anchor = CommitmentAnchor::ConsignmentCreated,
        public CommitmentCalculation $calculation = CommitmentCalculation::Elapsed,
        public ?int $durationValue = null,
        public CommitmentDurationUnit $durationUnit = CommitmentDurationUnit::Hour,
        public int $dayOffset = 0,
        public array $windowCodes = [],
        public int $riskThresholdMinutes = 120,
    ) {}
}
