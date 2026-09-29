<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Brick\Math\BigDecimal;
use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;

final readonly class TariffRuleDefinition
{
    public function __construct(
        public TariffRuleSelector $selector,
        public CalculationMethod $method,
        public ?BigDecimal $rangeFrom,
        public ?BigDecimal $rangeTo,
        public ?int $fixedAmount,
        public ?string $unitRate,
        public ?int $percentageBps,
        public ?int $minimumAmount,
        public ?int $maximumAmount,
        public AmountRoundingMode $roundingMode,
        public ?int $roundingStep,
    ) {}

    public function sameRange(self $other): bool
    {
        return $this->equalBound($this->rangeFrom, $other->rangeFrom) && $this->equalBound($this->rangeTo, $other->rangeTo);
    }

    public function overlaps(self $other): bool
    {
        if (! $this->validRange() || ! $other->validRange()) {
            return false;
        }
        if ($this->rangeTo !== null && $other->rangeFrom !== null && $this->rangeTo->isLessThanOrEqualTo($other->rangeFrom)) {
            return false;
        }

        return ! ($other->rangeTo !== null && $this->rangeFrom !== null && $other->rangeTo->isLessThanOrEqualTo($this->rangeFrom));
    }

    public function validRange(): bool
    {
        return $this->rangeFrom === null || $this->rangeTo === null || $this->rangeFrom->isLessThan($this->rangeTo);
    }

    private function equalBound(?BigDecimal $left, ?BigDecimal $right): bool
    {
        return $left === null || $right === null ? $left === $right : $left->isEqualTo($right);
    }
}
