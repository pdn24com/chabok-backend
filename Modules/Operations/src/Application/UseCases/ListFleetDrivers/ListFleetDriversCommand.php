<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetDrivers;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\FleetFiltersDto;

final readonly class ListFleetDriversCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public FleetFiltersDto $filters) {}
}
