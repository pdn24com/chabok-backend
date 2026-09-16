<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListAccessibleNodes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListAccessibleNodesHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler $resolveContext,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
    )
    {
    }

    public function handle(ListAccessibleNodesCommand $command): ListAccessibleNodesResult
    {
        return new ListAccessibleNodesResult($this->execute($command->actor));
    }

    private function execute(AuthenticatedPrincipal $actor): array
    {
        $this->authorizationGuard->assertPermission($actor, 'node_context.view', $actor->hqId);
        $nodeIds = $this->scopedAccess->nodes($this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($actor))->data, 'node_context.view');
        if ($nodeIds === []) {
            return [];
        }
        return $this->repository->activeNodes($actor->hqId, $nodeIds);
    }
}
