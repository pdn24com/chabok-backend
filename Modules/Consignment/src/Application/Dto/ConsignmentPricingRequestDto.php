<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ConsignmentPricingRequestDto
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public ConsignmentDraftDto $input,
        public string $requestId) {}
}
