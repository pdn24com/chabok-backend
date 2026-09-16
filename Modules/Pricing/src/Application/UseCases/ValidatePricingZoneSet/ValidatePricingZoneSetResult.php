<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidatePricingZoneSet;

final readonly class ValidatePricingZoneSetResult
{
    public function __construct(public array $data)
    {
    }
}
