<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CalculateConsignmentQuote;

final readonly class CalculateConsignmentQuoteResult
{
    public function __construct(public array $data)
    {
    }
}
