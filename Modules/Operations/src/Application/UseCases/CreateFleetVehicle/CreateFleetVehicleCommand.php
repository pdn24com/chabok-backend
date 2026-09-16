<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetVehicle;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateFleetVehicleCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
