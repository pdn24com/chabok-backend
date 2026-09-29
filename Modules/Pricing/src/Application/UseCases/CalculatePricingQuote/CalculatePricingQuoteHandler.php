<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CalculatePricingQuote;

use Modules\Pricing\Application\Contracts\QuoteCalculatorInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;

final readonly class CalculatePricingQuoteHandler
{
    public function __construct(private QuoteCalculatorInterface $quoteCalculator) {}

    public function handle(CalculatePricingQuoteCommand $command): PricingQuoteRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $idempotencyKey = $command->idempotencyKey;

        return $this->quoteCalculator->calculate($actor, $input, $idempotencyKey);
    }
}
