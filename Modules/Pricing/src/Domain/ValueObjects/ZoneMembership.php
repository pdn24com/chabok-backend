<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\ZoneMemberType;

final readonly class ZoneMembership
{
    public function __construct(public string $zoneId, public ZoneMemberType $type, public string $reference,
        public ?string $rangeEnd, public ?string $cityId, public ?string $provinceId) {}

    public function sameReference(self $other): bool
    {
        $reference = $this->cityId ?? $this->provinceId ?? mb_strtolower($this->reference);
        $otherReference = $other->cityId ?? $other->provinceId ?? mb_strtolower($other->reference);

        return $this->type === $other->type && $reference === $otherReference && ($this->rangeEnd ?? '') === ($other->rangeEnd ?? '');
    }

    public function overlapsPostalRange(self $other): bool
    {
        return $this->type === ZoneMemberType::POSTAL_RANGE && $other->type === ZoneMemberType::POSTAL_RANGE
            && strlen($this->reference) === strlen($other->reference)
            && strcmp($this->reference, $other->rangeEnd ?? '') <= 0 && strcmp($other->reference, $this->rangeEnd ?? '') <= 0;
    }
}
