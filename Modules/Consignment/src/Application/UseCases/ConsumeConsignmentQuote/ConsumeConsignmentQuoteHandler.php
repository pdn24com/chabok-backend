<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote;

final readonly class ConsumeConsignmentQuoteHandler
{
    public function __construct(private \Modules\Consignment\Application\Contracts\QuoteBundleStore $store)
    {
    }

    public function handle(ConsumeConsignmentQuoteCommand $command): ConsumeConsignmentQuoteResult
    {
        $this->execute($command->quoteId);
        return new ConsumeConsignmentQuoteResult();
    }

    private function execute(string $quoteId): void
    {
        $this->store->forget($quoteId);
    }
}
