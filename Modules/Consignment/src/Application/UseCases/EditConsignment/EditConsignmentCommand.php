<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\EditConsignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class EditConsignmentCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $consignmentId,
        public array $changes,
        public string $correlationId,
    )
    {
    }
}
