<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListDeliveryTasks;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListDeliveryTasksHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\DeliveryAccessGuard $deliveryAccessGuard,
        private \Modules\Operations\Application\Services\DeliveryTaskReader $deliveryTaskReader,
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
    )
    {
    }

    public function handle(ListDeliveryTasksCommand $command): ListDeliveryTasksResult
    {
        return new ListDeliveryTasksResult($this->execute($command->actor, $command->nodeId, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $filters = []): array
    {
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.view');
        return array_map(fn($row): array => $this->deliveryTaskReader->summary($row), $this->tasks->forNode($actor->hqId, $nodeId, $filters));
    }
}
