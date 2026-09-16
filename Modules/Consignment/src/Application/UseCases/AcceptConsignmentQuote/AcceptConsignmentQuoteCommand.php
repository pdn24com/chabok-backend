<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\AcceptConsignmentQuote;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AcceptConsignmentQuoteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $purpose,
        public array $input,
        public array $acceptedQuote,
        public ?string $consignmentId = null,
        public ?int $expectedVersion = null,
    )
    {
    }
}
