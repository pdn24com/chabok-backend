<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\RejectPricingQuote;

final readonly class RejectPricingQuoteResult
{
    public function __construct(public array $data)
    {
    }
}
