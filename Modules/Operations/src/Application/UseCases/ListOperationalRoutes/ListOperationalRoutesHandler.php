<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListOperationalRoutes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListOperationalRoutesHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\OperationalDirectoryAccess $operationalDirectoryAccess,
        private \Modules\Operations\Application\Repositories\OperationalDirectoryRepository $directory,
    )
    {
    }

    public function handle(ListOperationalRoutesCommand $command): ListOperationalRoutesResult
    {
        return new ListOperationalRoutesResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->operationalDirectoryAccess->access($actor, $nodeId, 'live_operations.view', 'LiveOperations');
        return $this->directory->routes($actor->hqId);
    }
}
