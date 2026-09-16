<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CalculatePricingQuote;

final readonly class CalculatePricingQuoteResult
{
    public function __construct(public array $data)
    {
    }
}
