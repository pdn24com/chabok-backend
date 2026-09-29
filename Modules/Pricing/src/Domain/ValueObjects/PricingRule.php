<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\ChargeCategory;
use Modules\Pricing\Domain\Enums\PricingBasis;

final readonly class PricingRule
{
    /** @param list<string> $basisChargeCodes */
    public function __construct(
        public string $id,
        public string $chargeTypeId,
        public string $chargeCode,
        public string $title,
        public ChargeCategory $category,
        public CalculationMethod $method,
        public PricingBasis $basis,
        public int $priority,
        public ?string $accountingMappingKey,
        public ?float $rangeFrom = null,
        public ?float $rangeTo = null,
        public ?int $fixedAmount = null,
        public ?string $unitRate = null,
        public ?int $percentageBps = null,
        public ?int $minimumAmount = null,
        public ?int $maximumAmount = null,
        public AmountRoundingMode $roundingMode = AmountRoundingMode::NONE,
        public ?int $roundingStep = null,
        public array $basisChargeCodes = [],
        public PricingConditions $conditions = new PricingConditions,
        public bool $taxable = true,
        public ?string $serviceTariffVersionId = null,
        public int|float|string|null $incrementalStep = null,
        public int|float|string|null $incrementalStepKg = null,
    ) {}
}
