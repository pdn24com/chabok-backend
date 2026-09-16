<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetDrivers;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListFleetDriversHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
    )
    {
    }

    public function handle(ListFleetDriversCommand $command): ListFleetDriversResult
    {
        return new ListFleetDriversResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->fleetAccessGuard->access($actor, 'fleet.driver.view');
        $page = $this->fleet->paginateDrivers($actor->hqId, $this->fleetAccessGuard->scopeNodes($actor, 'fleet.driver.view'), $filters);
        $ids = array_map(fn($row): string => (string) $row->driver_id, $page->rows);
        $capabilities = $this->fleet->capabilitiesForDrivers($ids);
        $rows = array_map(fn($row): array => $this->fleetProjection->driver((array) $row, $capabilities[(string) $row->driver_id] ?? []), $page->rows);
        return new Page($rows, $page->page, $page->pageSize, $page->totalRows);
    }
}
