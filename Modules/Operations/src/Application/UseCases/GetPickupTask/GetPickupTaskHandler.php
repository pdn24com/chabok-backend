<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetPickupTask;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final readonly class GetPickupTaskHandler
{
    public function __construct(
        private PickupAccessGuardInterface $pickupAccessGuard,
        private PickupTaskRepositoryInterface $pickupTaskRepository,
    ) {}

    public function handle(GetPickupTaskCommand $command): PickupTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.view');
        $row = $this->pickupTaskRepository->findDetailAtNode($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
