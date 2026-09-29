<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreatePickupTask;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;
use Modules\Operations\Application\Contracts\PickupTaskRecorderInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final readonly class CreatePickupTaskHandler
{
    public function __construct(
        private PickupAccessGuardInterface $pickupAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private PickupTaskRecorderInterface $pickupTaskRecorder,
        private GetPickupTaskHandler $getPickupTaskHandler,
        private ConsignmentRepositoryInterface $consignmentRepository,
        private PickupTaskRepositoryInterface $pickupTaskRepository,
    ) {}

    public function handle(CreatePickupTaskCommand $command): PickupTaskRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $consignmentId = $command->consignmentId;
        $correlationId = $command->correlationId;
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.create');
        $id = $this->connection->transaction(function () use ($actor, $nodeId, $consignmentId, $correlationId): string {
            $consignment = $this->consignmentRepository->findConfirmedAtPickupNode($actor->hqId, $consignmentId, $nodeId);
            if ($consignment === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_confirmed_consignment_pickup_node_can_create');
            }
            $existing = $this->pickupTaskRepository->findForConsignment($actor->hqId, $consignmentId);
            if ($existing !== null) {
                return (string) $existing->pickup_task_id;
            }
            $task = new PickupTaskRecord;
            $task->forceFill([

                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'node_id' => $nodeId,
                'status' => 'PENDING',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $id = (string) $task->getKey();
            $this->pickupTaskRecorder->record($actor, 'PICKUP_TASK_CREATED', $id, $consignmentId, 'PENDING', $correlationId);

            return $id;
        }, attempts: 3);

        return $this->getPickupTaskHandler->handle(new GetPickupTaskCommand($actor, $nodeId, $id));
    }
}
