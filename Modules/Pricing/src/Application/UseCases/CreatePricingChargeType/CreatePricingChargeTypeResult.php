<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingChargeType;

final readonly class CreatePricingChargeTypeResult
{
    public function __construct(public array $data)
    {
    }
}
