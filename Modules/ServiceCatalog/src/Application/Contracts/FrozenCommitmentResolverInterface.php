<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use DateTimeImmutable;
use Modules\ServiceCatalog\Application\Dto\FrozenDeliveryCommitmentDto;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentResolution;

/** Resolves completion solely against the commitment snapshot frozen at issuance. */
interface FrozenCommitmentResolverInterface
{
    public function pickupCompleted(FrozenDeliveryCommitmentDto $snapshot, DateTimeImmutable $completedAt): CommitmentResolution;
}
