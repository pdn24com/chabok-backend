<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingChargeTypes;

final readonly class ListPricingChargeTypesResult
{
    public function __construct(public array $data)
    {
    }
}
