<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\PricingBasis;

final readonly class TariffRuleSelector
{
    public function __construct(
        public ?string $offeringVersionId,
        public ?string $optionVersionId,
        public ?string $originZoneId,
        public ?string $destinationZoneId,
        public string $chargeTypeId,
        public int $priority,
        public PricingBasis $basis,
    ) {}

    public function sameTarget(self $other): bool
    {
        return $this->offeringVersionId === $other->offeringVersionId
            && $this->optionVersionId === $other->optionVersionId
            && $this->originZoneId === $other->originZoneId
            && $this->destinationZoneId === $other->destinationZoneId
            && $this->chargeTypeId === $other->chargeTypeId
            && $this->priority === $other->priority;
    }
}
