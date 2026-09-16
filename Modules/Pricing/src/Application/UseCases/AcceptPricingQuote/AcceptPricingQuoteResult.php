<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptPricingQuote;

final readonly class AcceptPricingQuoteResult
{
    public function __construct(public array $data)
    {
    }
}
