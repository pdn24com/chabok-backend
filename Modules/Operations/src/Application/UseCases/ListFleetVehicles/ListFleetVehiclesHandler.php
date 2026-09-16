<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetVehicles;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListFleetVehiclesHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
    )
    {
    }

    public function handle(ListFleetVehiclesCommand $command): ListFleetVehiclesResult
    {
        return new ListFleetVehiclesResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->fleetAccessGuard->access($actor, 'fleet.vehicle.view');
        $page = $this->fleet->paginateVehicles($actor->hqId, $this->fleetAccessGuard->scopeNodes($actor, 'fleet.vehicle.view'), $filters);
        $rows = array_map(fn($row): array => $this->fleetProjection->vehicle((array) $row), $page->rows);
        return new Page($rows, $page->page, $page->pageSize, $page->totalRows);
    }
}
