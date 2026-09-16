<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableVehicles;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListAvailableVehiclesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
