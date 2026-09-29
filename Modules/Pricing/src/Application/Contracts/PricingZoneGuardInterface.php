<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\PolygonValidationFailureDto;

interface PricingZoneGuardInterface
{
    public function validatePolygons(array $zones): array;

    public function inspectPolygons(array $zones): ?PolygonValidationFailureDto;

    public function resolveGeography(array $zones): void;
}
