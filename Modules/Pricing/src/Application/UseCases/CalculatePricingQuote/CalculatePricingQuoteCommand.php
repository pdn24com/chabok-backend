<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CalculatePricingQuote;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CalculatePricingQuoteCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $idempotencyKey)
    {
    }
}
