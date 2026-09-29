<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\RejectPricingQuote;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;

final readonly class RejectPricingQuoteHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ClockInterface $clock,
        private PricingReaderInterface $pricingReader,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
    ) {}

    public function handle(RejectPricingQuoteCommand $command): PricingQuoteRecord
    {
        $actor = $command->actor;
        $quoteId = $command->quoteId;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.calculate', runtime: true);
        $this->pricingQuoteRepository->rejectOfferedQuote($actor->hqId, $quoteId, ['status' => 'REJECTED', 'updated_at' => $this->clock->now()]);

        return $this->pricingReader->quoteDetail($actor, $quoteId);
    }
}
