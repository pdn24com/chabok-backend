<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListAccessibleNodes;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class ListAccessibleNodesHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ScopedAccessInterface $scopedAccess,
        private ResolveContextHandler $resolveContextHandler,
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function handle(ListAccessibleNodesCommand $command): array
    {
        $actor = $command->actor;
        $this->authorizationGuard->assertPermission($actor, 'node_context.view', $actor->hqId);
        $nodeIds = $this->scopedAccess->nodes($this->resolveContextHandler->handle(new ResolveContextCommand($actor)), 'node_context.view');
        if ($nodeIds === []) {
            return [];
        }

        return $this->nodeRepository->directoryEntries((string) $actor->hqId, $nodeIds, true);
    }
}
