<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CalculatePricingQuote;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\QuoteInputDto;

final readonly class CalculatePricingQuoteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public QuoteInputDto $input,
        public string $idempotencyKey,
    ) {}
}
