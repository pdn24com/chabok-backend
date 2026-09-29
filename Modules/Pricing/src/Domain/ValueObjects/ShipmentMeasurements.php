<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class ShipmentMeasurements
{
    /** @param list<ParcelMeasurement> $parcels */
    public function __construct(public array $parcels, public ?ParcelMeasurement $aggregate = null,
        public int $declaredValueAmount = 0, public int $codAmount = 0, public bool $insuranceEnabled = false, public bool $codEnabled = false) {}
}
