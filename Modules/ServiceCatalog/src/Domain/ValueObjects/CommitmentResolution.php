<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class CommitmentResolution
{
    /** @param list<CommitmentWindowInstance> $windows */
    public function __construct(
        public CommitmentTimingPolicy $policy,
        public bool $awaitingOperation = false,
        public ?CarbonImmutable $computedAt = null,
        public array $windows = [],
        public ?CommitmentWindowInstance $selected = null,
    ) {}
}
