<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class CompiledMatrixRateRule
{
    public function __construct(
        public string $cellId,
        public ?string $serviceOfferingVersionId,
        public ?string $serviceOptionVersionId,
        public ?string $originZoneId,
        public string $destinationZoneId,
        public string $chargeTypeId,
        public int|float|string $rangeFrom,
        public int|float|string|null $rangeTo,
        public int|float|string $fixedAmount,
        public int|float|string|null $unitRate = null,
        public int|float|string|null $incrementalStepKg = null,
    ) {}
}
