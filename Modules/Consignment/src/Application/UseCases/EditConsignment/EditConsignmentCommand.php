<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\EditConsignment;

use Modules\Consignment\Application\Dto\ConsignmentEditDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class EditConsignmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
        public ConsignmentEditDto $changes,
        public string $correlationId,
    ) {}
}
