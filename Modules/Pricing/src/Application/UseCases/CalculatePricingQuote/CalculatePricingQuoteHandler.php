<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CalculatePricingQuote;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CalculatePricingQuoteHandler
{
    public function __construct(private \Modules\Pricing\Application\Services\QuoteCalculator $quoteCalculator)
    {
    }

    public function handle(CalculatePricingQuoteCommand $command): CalculatePricingQuoteResult
    {
        return new CalculatePricingQuoteResult($this->execute($command->actor, $command->input, $command->idempotencyKey));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $idempotencyKey): array
    {
        return $this->quoteCalculator->calculate($actor, $input, $idempotencyKey);
    }
}
