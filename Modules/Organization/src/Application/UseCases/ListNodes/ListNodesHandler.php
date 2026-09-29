<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListNodes;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class ListNodesHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ScopedAccessInterface $scopedAccess,
        private AccessContextResolverInterface $accessContextResolver,
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function handle(ListNodesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.view');

        $visibleIds = $this->scopedAccess->nodes($this->accessContextResolver->resolve($actor), 'network.node.view', false);

        return $this->nodeRepository->search($hqId, $visibleIds, $filters);
    }
}
