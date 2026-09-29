<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetVehicles;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\FleetFiltersDto;

final readonly class ListFleetVehiclesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public FleetFiltersDto $filters) {}
}
