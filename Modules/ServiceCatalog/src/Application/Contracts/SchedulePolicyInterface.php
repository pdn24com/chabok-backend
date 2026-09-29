<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;

interface SchedulePolicyInterface
{
    /** @param list<CommitmentWindow> $windows */
    public function validate(array $policy, array $windows, string $hqId): array;
}
