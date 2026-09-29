<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowUnavailability;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindowInstance;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;

interface ScheduleWindowsInterface
{
    /** @param Collection<int, CommitmentScheduleWindowRecord> $windows @return list<CommitmentWindowInstance> */
    public function pickupWindowOptions(Collection $windows, string $timezone, ?string $serviceDate): array;

    public function nextWindow(CommitmentScheduleWindowRecord $window, string $timezone, CarbonImmutable $now): ?CommitmentWindowInstance;

    public function windowInstance(?CommitmentScheduleWindowRecord $window, CommitmentWindowType $windowType, string $serviceDate, string $timezone): CommitmentWindowInstance;

    public function inspectWindow(?CommitmentScheduleWindowRecord $window, CommitmentWindowType $windowType, string $serviceDate, string $timezone): CommitmentWindowInstance|CommitmentWindowUnavailability;
}
