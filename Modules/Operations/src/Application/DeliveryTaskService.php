<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class DeliveryTaskService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksHandler $listDeliveryTasks,
        private \Modules\Operations\Application\UseCases\EnsurePendingDelivery\EnsurePendingDeliveryHandler $ensurePendingDelivery,
        private \Modules\Operations\Application\UseCases\ActivateManifestDelivery\ActivateManifestDeliveryHandler $activateManifestDelivery,
        private \Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskHandler $assignDeliveryTask,
        private \Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskHandler $completeDeliveryTask,
        private \Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskHandler $failDeliveryTask,
        private \Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskHandler $retryDeliveryTask,
        private \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler $getDeliveryTask,
    )
    {
    }

    public function list(AuthenticatedPrincipal $actor, string $nodeId, array $filters = []): array
    {
        return $this->listDeliveryTasks->handle(new \Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksCommand($actor, $nodeId, $filters))->data;
    }

    public function ensurePending(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId): string
    {
        return $this->ensurePendingDelivery->handle(new \Modules\Operations\Application\UseCases\EnsurePendingDelivery\EnsurePendingDeliveryCommand($actor, $nodeId, $consignmentId))->data;
    }

    public function activateFromManifest(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        string $driverId,
        string $manifestId,
    ): string
    {
        return $this->activateManifestDelivery->handle(new \Modules\Operations\Application\UseCases\ActivateManifestDelivery\ActivateManifestDeliveryCommand($actor, $nodeId, $consignmentId, $driverId, $manifestId))->data;
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
        return $this->assignDeliveryTask->handle(new \Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskCommand($actor, $nodeId, $id, $driverId, $expected, $correlationId))->data;
    }

    public function complete(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $recipientName,
        string $deliveredAt,
        ?string $note,
        string $correlationId,
    ): array
    {
        return $this->completeDeliveryTask->handle(new \Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskCommand($actor, $nodeId, $id, $expected, $recipientName, $deliveredAt, $note, $correlationId))->data;
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
        return $this->failDeliveryTask->handle(new \Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskCommand($actor, $nodeId, $id, $expected, $reasonCode, $reason, $correlationId))->data;
    }

    public function retry(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $reason,
        string $correlationId,
    ): array
    {
        return $this->retryDeliveryTask->handle(new \Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskCommand($actor, $nodeId, $id, $expected, $reason, $correlationId))->data;
    }

    public function get(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->getDeliveryTask->handle(new \Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand($actor, $nodeId, $id))->data;
    }
}
