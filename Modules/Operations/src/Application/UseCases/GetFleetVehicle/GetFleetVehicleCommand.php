<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetVehicle;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetFleetVehicleCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $vehicleId)
    {
    }
}
