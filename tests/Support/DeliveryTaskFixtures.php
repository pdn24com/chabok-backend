<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\DeliveryTaskFiltersDto;
use Modules\Operations\Application\UseCases\ActivateManifestDelivery\ActivateManifestDeliveryCommand;
use Modules\Operations\Application\UseCases\ActivateManifestDelivery\ActivateManifestDeliveryHandler;
use Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\AssignDeliveryTask\AssignDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\CompleteDeliveryTask\CompleteDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\EnsurePendingDelivery\EnsurePendingDeliveryCommand;
use Modules\Operations\Application\UseCases\EnsurePendingDelivery\EnsurePendingDeliveryHandler;
use Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\FailDeliveryTask\FailDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskCommand;
use Modules\Operations\Application\UseCases\GetDeliveryTask\GetDeliveryTaskHandler;
use Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksCommand;
use Modules\Operations\Application\UseCases\ListDeliveryTasks\ListDeliveryTasksHandler;
use Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskCommand;
/** Test fixture shorthand for focused use cases; no production facade. */
use Modules\Operations\Application\UseCases\RetryDeliveryTask\RetryDeliveryTaskHandler;
use Modules\Operations\Presentation\Http\Resources\DeliveryTaskDetailResource;
use Modules\Operations\Presentation\Http\Resources\DeliveryTaskResource;

final readonly class DeliveryTaskFixtures
{
    public function __construct(
        private ListDeliveryTasksHandler $listDeliveryTasks,
        private EnsurePendingDeliveryHandler $ensurePendingDelivery,
        private ActivateManifestDeliveryHandler $activateManifestDelivery,
        private AssignDeliveryTaskHandler $assignDeliveryTask,
        private CompleteDeliveryTaskHandler $completeDeliveryTask,
        private FailDeliveryTaskHandler $failDeliveryTask,
        private RetryDeliveryTaskHandler $retryDeliveryTask,
        private GetDeliveryTaskHandler $getDeliveryTask,
    ) {}

    public function list(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $filters = [],
    ): array {
        return DeliveryTaskResource::collection($this->listDeliveryTasks->handle(new ListDeliveryTasksCommand($actor, $nodeId, DeliveryTaskFiltersDto::fromValidated($filters))))->resolve();
    }

    public function ensurePending(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
    ): string {
        return $this->ensurePendingDelivery->handle(new EnsurePendingDeliveryCommand($actor, $nodeId, $consignmentId));
    }

    public function activateFromManifest(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        string $driverId,
        string $manifestId,
    ): string {
        return $this->activateManifestDelivery->handle(new ActivateManifestDeliveryCommand($actor, $nodeId, $consignmentId, $driverId, $manifestId));
    }

    public function assign(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        string $driverId,
        int $expected,
        string $correlationId,
    ): array {
        return (new DeliveryTaskDetailResource($this->assignDeliveryTask->handle(new AssignDeliveryTaskCommand($actor, $nodeId, $id, $driverId, $expected, $correlationId))))->resolve();
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
    ): array {
        return (new DeliveryTaskDetailResource($this->completeDeliveryTask->handle(new CompleteDeliveryTaskCommand($actor, $nodeId, $id, $expected, $recipientName, $deliveredAt, $note, $correlationId))))->resolve();
    }

    public function fail(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $reasonCode,
        string $reason,
        string $correlationId,
    ): never {
        $this->failDeliveryTask->handle(new FailDeliveryTaskCommand($actor, $nodeId, $id, $expected, $reasonCode, $reason, $correlationId));
    }

    public function retry(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $reason,
        string $correlationId,
    ): array {
        return (new DeliveryTaskDetailResource($this->retryDeliveryTask->handle(new RetryDeliveryTaskCommand($actor, $nodeId, $id, $expected, $reason, $correlationId))))->resolve();
    }

    public function get(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): array {
        return (new DeliveryTaskDetailResource($this->getDeliveryTask->handle(new GetDeliveryTaskCommand($actor, $nodeId, $id))))->resolve();
    }
}
