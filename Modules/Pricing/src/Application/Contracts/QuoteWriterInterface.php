<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\CalculatedQuoteDto;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;

interface QuoteWriterInterface
{
    public function write(AuthenticatedPrincipal $actor, string $idempotencyKey, CalculatedQuoteDto $quote): PricingQuoteRecord;
}
