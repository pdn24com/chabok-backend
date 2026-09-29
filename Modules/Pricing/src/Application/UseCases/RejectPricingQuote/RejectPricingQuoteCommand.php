<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\RejectPricingQuote;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RejectPricingQuoteCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $quoteId) {}
}
