<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetVehicle;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\VehicleCreationDto;

final readonly class CreateFleetVehicleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public VehicleCreationDto $input,
        public string $correlationId,
    ) {}
}
