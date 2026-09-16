<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListPickupTasks;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPickupTasksHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\PickupAccessGuard $pickupAccessGuard,
        private \Modules\Operations\Application\Services\PickupTaskReader $pickupTaskReader,
        private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks,
    )
    {
    }

    public function handle(ListPickupTasksCommand $command): ListPickupTasksResult
    {
        return new ListPickupTasksResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.view');
        return array_map(fn($row) => $this->pickupTaskReader->item($row), $this->tasks->forNode($actor->hqId, $nodeId));
    }
}
