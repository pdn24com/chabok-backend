<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListAreas;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Application\Data\Page;

final readonly class ListAreasHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
    )
    {
    }

    public function handle(ListAreasCommand $command): ListAreasResult
    {
        return new ListAreasResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.view');
        return $this->network->paginateAreas($hqId, $filters, $this->networkAccessGuard->scopeAreas($actor, 'network.area.view'));
    }
}
