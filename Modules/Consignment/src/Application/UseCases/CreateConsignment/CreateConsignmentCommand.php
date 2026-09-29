<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateConsignment;

use Modules\Consignment\Application\Dto\ConsignmentCreationDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateConsignmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public ConsignmentCreationDto $input,
        public string $correlationId,
    ) {}
}
