<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\AcceptPricingQuote;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AcceptPricingQuoteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $quoteId,
        public string $objectType,
        public string $objectId,
        public string $inputFingerprint,
        public string $idempotencyKey,
    )
    {
    }
}
