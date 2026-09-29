<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListAreas;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;

final readonly class ListAreasHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private AreaRepositoryInterface $areaRepository,
    ) {}

    public function handle(ListAreasCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.view');

        $visibleIds = $this->networkAccessGuard->scopeAreas($actor, 'network.area.view');

        return $this->areaRepository->search($hqId, $visibleIds, $filters);
    }
}
