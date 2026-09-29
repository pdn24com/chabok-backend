<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class AcceptedQuoteReferenceDto
{
    public function __construct(public string $quoteId, public int $quoteVersion, public string $optionId) {}
}
