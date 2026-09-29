<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;

final readonly class CustomerDraftDto
{
    public function __construct(
        public string $firstName,
        public string $familyName,
        public string $displayName,
        public CustomerKind $kind,
        public CustomerPhase $phase,
        public CustomerAddressDto $address,
        /** The mobile number as typed; it becomes the default MOBILE contact point of the record. */
        public string $mobile,
        public ?string $customerCode = null,
        /** The single primary industry of the record, when the operator selected one. */
        public ?string $industryId = null,
        public ?string $assigneeId = null,
    ) {}
}
