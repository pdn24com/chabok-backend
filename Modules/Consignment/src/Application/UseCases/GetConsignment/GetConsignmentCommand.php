<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetConsignmentCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public string $consignmentId)
    {
    }
}
