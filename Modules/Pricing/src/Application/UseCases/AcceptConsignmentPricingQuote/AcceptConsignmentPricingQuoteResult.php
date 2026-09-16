<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote;

final readonly class AcceptConsignmentPricingQuoteResult
{
    public function __construct(public string $snapshotId)
    {
    }
}
