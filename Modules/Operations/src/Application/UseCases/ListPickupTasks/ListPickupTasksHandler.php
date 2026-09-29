<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListPickupTasks;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;

final readonly class ListPickupTasksHandler
{
    public function __construct(
        private PickupAccessGuardInterface $pickupAccessGuard,
        private PickupTaskRepositoryInterface $pickupTaskRepository,
    ) {}

    public function handle(ListPickupTasksCommand $command): Collection
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.view');

        return $this->pickupTaskRepository->listAtNode($actor->hqId, $nodeId);
    }
}
