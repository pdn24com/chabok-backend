<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Customer\Domain\Enums\CustomerPhase;

/**
 * Profile values submitted by the editor. The profile writer always persists its complete field set, and
 * null clears columns that allow it. The *Specified flags only distinguish an explicit empty request from
 * a request with no profile changes at all. updated_at is server owned and never accepted as input.
 */
final readonly class CustomerProfileChangesDto
{
    public function __construct(
        public ?CustomerKind $kind = null,
        public ?CustomerPhase $phase = null,
        public ?CustomerLifecycle $lifecycle = null,
        public ?string $displayName = null,
        public bool $displayNameSpecified = false,
        public ?string $customerCode = null,
        public bool $customerCodeSpecified = false,
        public ?string $assigneeId = null,
        public bool $assigneeSpecified = false,
        /** The industry to carry crm_customer_industry.is_primary; null clears the primary flag. */
        public ?string $primaryIndustryId = null,
        public bool $primaryIndustrySpecified = false,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->kind === null && $this->phase === null && $this->lifecycle === null
            && ! $this->displayNameSpecified && ! $this->customerCodeSpecified
            && ! $this->assigneeSpecified && ! $this->primaryIndustrySpecified;
    }
}
