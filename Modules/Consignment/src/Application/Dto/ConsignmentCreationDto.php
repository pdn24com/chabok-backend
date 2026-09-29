<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class ConsignmentCreationDto
{
    public function __construct(public ConsignmentDraftDto $draft, public AcceptedQuoteReferenceDto $acceptedQuote) {}
}
