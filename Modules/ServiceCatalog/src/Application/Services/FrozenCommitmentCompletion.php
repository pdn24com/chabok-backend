<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Modules\ServiceCatalog\Application\Contracts\CommitmentClockInterface;
use Modules\ServiceCatalog\Application\Contracts\FrozenCommitmentCompletionInterface;
use Modules\ServiceCatalog\Application\Dto\FrozenDeliveryCommitmentDto;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentContext;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentResolution;

/** Resolves an operational anchor only against the policy frozen at issuance. */
final class FrozenCommitmentCompletion implements FrozenCommitmentCompletionInterface
{
    public function __construct(private CommitmentClockInterface $commitmentClock) {}

    public function pickupCompleted(FrozenDeliveryCommitmentDto $snapshot, DateTimeImmutable $completedAt): CommitmentResolution
    {
        $completed = CarbonImmutable::instance($completedAt);
        $context = new CommitmentContext(acceptedAt: $snapshot->acceptedAt ?? $completed, pickupCompletedAt: $completed,
            deliveryWindowCode: $snapshot->requestedWindowCode);

        return $this->commitmentClock->resolve($snapshot->policy, $snapshot->windows, $context,
            $snapshot->timezone, $snapshot->includeHolidays, true, CommitmentWindowType::Delivery);
    }
}
