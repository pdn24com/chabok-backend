<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use DateTimeImmutable;

interface CustomerPrimaryIndustryManagerInterface
{
    /** Moves the primary flag of a customer to the named industry, or clears it when none is named. */
    public function move(string $hqId, string $customerId, ?string $industryId, string $actorId, DateTimeImmutable $at): void;
}
