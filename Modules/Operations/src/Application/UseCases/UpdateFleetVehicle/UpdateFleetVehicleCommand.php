<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetVehicle;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\VehicleChangesDto;

final readonly class UpdateFleetVehicleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $vehicleId,
        public VehicleChangesDto $input,
        public string $correlationId,
    ) {}
}
