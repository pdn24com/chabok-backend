<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class ConsignmentEditDto
{
    public function __construct(public int $expectedVersion, public string $changeReason, public ?string $note, public ConsignmentDraftDto $changes, public ?AcceptedQuoteReferenceDto $acceptedQuote) {}
}
