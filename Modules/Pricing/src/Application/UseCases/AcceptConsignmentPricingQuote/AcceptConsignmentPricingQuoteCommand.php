<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote;

final readonly class AcceptConsignmentPricingQuoteCommand
{
    public function __construct(
        public string $hqId,
        public string $consignmentId,
        public string $actorId,
        public int $version,
        public array $accepted,
    )
    {
    }
}
