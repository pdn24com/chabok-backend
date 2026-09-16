<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetVehicle;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateFleetVehicleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $vehicleId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
