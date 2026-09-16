<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreatePickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreatePickupTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\PickupAccessGuard $pickupAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\PickupTaskRecorder $pickupTaskRecorder,
        private \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler $getPickupTask,
    )
    {
    }

    public function handle(CreatePickupTaskCommand $command): CreatePickupTaskResult
    {
        return new CreatePickupTaskResult($this->execute($command->actor, $command->nodeId, $command->consignmentId, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $correlationId): array
    {
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.create');
        $id = $this->transactions->run(function () use ($actor, $nodeId, $consignmentId, $correlationId): string {
            $consignment = $this->tasks->confirmedConsignment($actor->hqId, $nodeId, $consignmentId);
            if ($consignment === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a confirmed Consignment at its pickup node can create a Pickup Task.');
            }
            $existing = $this->tasks->forConsignment($actor->hqId, $consignmentId);
            if ($existing !== null) {
                return (string) $existing->pickup_task_id;
            }
            $id = $this->identifiers->uuid();
            $this->tasks->insert([
                'pickup_task_id' => $id,
                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'node_id' => $nodeId,
                'status' => 'PENDING',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->pickupTaskRecorder->record($actor, 'PICKUP_TASK_CREATED', $id, $consignmentId, 'PENDING', $correlationId);
            return $id;
        });
        return $this->getPickupTask->handle(new \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand($actor, $nodeId, $id))->data;
    }
}
