<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Modules\Customer\Domain\Enums\CustomerPhase;

/** One existing record a mobile number or an email address already belongs to, and which of the two matched. */
final readonly class CustomerDuplicateDto
{
    public function __construct(
        public string $customerId,
        public ?string $displayName,
        public CustomerPhase $phase,
        public string $matchedOn,
    ) {}
}
