<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetVehicles;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListFleetVehiclesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
