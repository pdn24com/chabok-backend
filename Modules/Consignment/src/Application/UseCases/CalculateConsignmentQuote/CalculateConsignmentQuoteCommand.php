<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CalculateConsignmentQuote;

use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CalculateConsignmentQuoteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $purpose,
        public ConsignmentDraftDto $input,
        public ?string $consignmentId,
        public ?int $expectedVersion,
    ) {}
}
