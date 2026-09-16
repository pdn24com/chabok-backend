<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\RejectPricingQuote;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RejectPricingQuoteHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
    )
    {
    }

    public function handle(RejectPricingQuoteCommand $command): RejectPricingQuoteResult
    {
        return new RejectPricingQuoteResult($this->execute($command->actor, $command->quoteId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        $this->pricing->rejectQuote($actor->hqId, $quoteId, $this->clock->now());
        return $this->pricingReader->quoteDetail($actor, $quoteId);
    }
}
