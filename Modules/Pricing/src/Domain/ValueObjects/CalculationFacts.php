<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\PricingFact;

final readonly class CalculationFacts
{
    public function __construct(
        public int|float|null $actualWeightKg = null,
        public int|float|null $billableWeightKg = null,
        public ?int $parcelCount = null,
        public ?int $declaredValueAmount = null,
        public ?int $codAmount = null,
        public ?bool $insuranceEnabled = null,
        public ?bool $codEnabled = null,
        public ?bool $remoteArea = null,
        public ?string $weightEvidence = null,
    ) {}

    public function value(PricingFact $fact): int|float|bool|string|null
    {
        return match ($fact) {
            PricingFact::ACTUAL_WEIGHT => $this->actualWeightKg,
            PricingFact::BILLABLE_WEIGHT => $this->billableWeightKg,
            PricingFact::PARCEL_COUNT => $this->parcelCount,
            PricingFact::DECLARED_VALUE => $this->declaredValueAmount,
            PricingFact::COD_AMOUNT => $this->codAmount,
            PricingFact::INSURANCE_ENABLED => $this->insuranceEnabled,
            PricingFact::COD_ENABLED => $this->codEnabled,
            PricingFact::REMOTE_AREA => $this->remoteArea,
            PricingFact::WEIGHT_EVIDENCE => $this->weightEvidence,
        };
    }
}
