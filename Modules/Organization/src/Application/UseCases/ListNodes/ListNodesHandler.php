<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListNodes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Application\Data\Page;

final readonly class ListNodesHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
    )
    {
    }

    public function handle(ListNodesCommand $command): ListNodesResult
    {
        return new ListNodesResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.view');
        return $this->network->paginateNodes($hqId, $filters, $this->scopedAccess->nodes($this->authorization->resolve($actor), 'network.node.view', false));
    }
}
