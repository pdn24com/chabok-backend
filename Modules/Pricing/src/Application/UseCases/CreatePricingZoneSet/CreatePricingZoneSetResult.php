<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingZoneSet;

final readonly class CreatePricingZoneSetResult
{
    public function __construct(public array $data)
    {
    }
}
