<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/**
 * What the conversion form may change on the way from lead to customer. A name field that is null was not
 * sent and keeps its stored value. The merge fields are carried only so the handler can refuse them:
 * merging a lead into an existing customer is an open product decision.
 */
final readonly class LeadConversionDto
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $familyName = null,
        public ?string $displayName = null,
        public ?string $customerCode = null,
        public ?string $mergeIntoCustomerId = null,
        public bool $confirmMerge = false,
    ) {}
}
