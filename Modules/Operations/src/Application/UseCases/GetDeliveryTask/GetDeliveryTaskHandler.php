<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetDeliveryTask;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final readonly class GetDeliveryTaskHandler
{
    public function __construct(
        private DeliveryAccessGuardInterface $deliveryAccessGuard,
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
    ) {}

    public function handle(GetDeliveryTaskCommand $command): DeliveryTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.view');
        $row = $this->deliveryTaskRepository->findDetailAtNode($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
