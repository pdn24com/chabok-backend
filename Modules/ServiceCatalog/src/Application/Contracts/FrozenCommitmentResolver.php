<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

/** Resolves completion solely against the commitment snapshot frozen at issuance. */

interface FrozenCommitmentResolver
{
    public function pickupCompleted(array $snapshot, string $completedAt): ?array;
}
