<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote;

use Modules\Consignment\Application\Contracts\QuoteBundleStoreInterface;

final readonly class ConsumeConsignmentQuoteHandler
{
    public function __construct(private QuoteBundleStoreInterface $quoteBundleStore) {}

    public function handle(ConsumeConsignmentQuoteCommand $command): ConsumeConsignmentQuoteResult
    {
        $quoteId = $command->quoteId;
        $this->quoteBundleStore->forget($quoteId);

        return new ConsumeConsignmentQuoteResult;
    }
}
