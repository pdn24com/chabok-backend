<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentContext;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentResolution;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentTimingPolicy;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;

interface CommitmentClockInterface
{
    /** @param list<CommitmentWindow> $windows */
    public function resolve(CommitmentTimingPolicy $policy, array $windows, CommitmentContext $context, string $timezone, bool $includeHolidays, bool $requireSelection, CommitmentWindowType $kind, ?callable $isHoliday = null): CommitmentResolution;
}
