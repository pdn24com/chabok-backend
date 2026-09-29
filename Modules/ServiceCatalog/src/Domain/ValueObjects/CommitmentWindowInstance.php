<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class CommitmentWindowInstance
{
    public function __construct(
        public CommitmentWindow $window,
        public string $serviceDate,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public CarbonImmutable $bookingCutoffAt,
        public string $timezone,
        public int $dayOffset,
    ) {}
}
