<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CalculateConsignmentQuote;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CalculateConsignmentQuoteCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $purpose,
        public array $input,
        public ?string $consignmentId,
        public ?int $expectedVersion,
    )
    {
    }
}
