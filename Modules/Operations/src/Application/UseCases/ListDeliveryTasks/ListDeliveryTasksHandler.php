<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListDeliveryTasks;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;

final readonly class ListDeliveryTasksHandler
{
    public function __construct(
        private DeliveryAccessGuardInterface $deliveryAccessGuard,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
    ) {}

    public function handle(ListDeliveryTasksCommand $command): Collection
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $filters = $command->filters;
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.view');

        return $this->deliveryTaskRepository->listAtNode($actor->hqId, $nodeId, $filters);
    }
}
