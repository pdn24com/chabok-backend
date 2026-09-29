<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote;

final readonly class ConsumeConsignmentQuoteCommand
{
    public function __construct(public string $quoteId) {}
}
