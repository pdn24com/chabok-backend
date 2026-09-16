<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\AcceptConsignmentQuote;

final readonly class AcceptConsignmentQuoteResult
{
    public function __construct(public array $data)
    {
    }
}
