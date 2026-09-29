<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;

final readonly class CommitmentWindow
{
    /** @param list<int> $weekdays */
    public function __construct(
        public string $code,
        public CommitmentWindowType $type,
        public string $label,
        public string $startTime,
        public string $endTime,
        public string $bookingCutoffTime,
        public array $weekdays,
        public int $riskThresholdMinutes = 120,
        public int $dayOffset = 0,
        public bool $active = true,
    ) {}

}
