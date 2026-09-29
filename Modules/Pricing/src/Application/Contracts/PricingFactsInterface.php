<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\ShipmentMeasurements;
use Modules\Pricing\Domain\ValueObjects\WeightPricingPolicy;

interface PricingFactsInterface
{
    public function facts(ShipmentMeasurements $shipment, WeightPricingPolicy $policy, bool $remoteArea): CalculationFacts;
}
