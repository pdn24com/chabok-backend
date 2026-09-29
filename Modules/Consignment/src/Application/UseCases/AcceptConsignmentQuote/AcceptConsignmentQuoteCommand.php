<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\AcceptConsignmentQuote;

use Modules\Consignment\Application\Dto\AcceptedQuoteReferenceDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AcceptConsignmentQuoteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $purpose,
        public ConsignmentDraftDto $input,
        public AcceptedQuoteReferenceDto $acceptedQuote,
        public ?string $consignmentId = null,
        public ?int $expectedVersion = null,
    ) {}
}
