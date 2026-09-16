<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion;

final readonly class UpdatePricingZoneVersionResult
{
    public function __construct(public array $data)
    {
    }
}
