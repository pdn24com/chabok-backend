<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PickupTaskService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksHandler $listPickupTasks,
        private \Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskHandler $createPickupTask,
        private \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler $getPickupTask,
        private \Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskHandler $assignPickupTask,
        private \Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskHandler $completePickupTask,
        private \Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskHandler $failPickupTask,
    )
    {
    }

    public function list(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->listPickupTasks->handle(new \Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksCommand($actor, $nodeId))->data;
    }

    public function create(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId, string $correlationId): array
    {
        return $this->createPickupTask->handle(new \Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskCommand($actor, $nodeId, $consignmentId, $correlationId))->data;
    }

    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->getPickupTask->handle(new \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand($actor, $nodeId, $id))->data;
    }

    public function assign(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        string $driverId,
        int $expected,
        string $correlationId,
    ): array
    {
        return $this->assignPickupTask->handle(new \Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskCommand($actor, $nodeId, $id, $driverId, $expected, $correlationId))->data;
    }

    public function complete(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        return $this->completePickupTask->handle(new \Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskCommand($actor, $nodeId, $id, $expected, $correlationId))->data;
    }

    public function fail(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $reasonCode,
        string $reason,
        string $correlationId,
    ): array
    {
        return $this->failPickupTask->handle(new \Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskCommand($actor, $nodeId, $id, $expected, $reasonCode, $reason, $correlationId))->data;
    }
}
