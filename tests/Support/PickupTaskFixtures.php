<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskCommand;
use Modules\Operations\Application\UseCases\AssignPickupTask\AssignPickupTaskHandler;
use Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskCommand;
use Modules\Operations\Application\UseCases\CompletePickupTask\CompletePickupTaskHandler;
use Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskCommand;
use Modules\Operations\Application\UseCases\CreatePickupTask\CreatePickupTaskHandler;
use Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskCommand;
use Modules\Operations\Application\UseCases\FailPickupTask\FailPickupTaskHandler;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand;
use Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler;
use Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksCommand;
use Modules\Operations\Application\UseCases\ListPickupTasks\ListPickupTasksHandler;
/** Test fixture shorthand for focused use cases; no production facade. */
use Modules\Operations\Presentation\Http\Resources\PickupTaskResource;

final readonly class PickupTaskFixtures
{
    public function __construct(
        private ListPickupTasksHandler $listPickupTasks,
        private CreatePickupTaskHandler $createPickupTask,
        private GetPickupTaskHandler $getPickupTask,
        private AssignPickupTaskHandler $assignPickupTask,
        private CompletePickupTaskHandler $completePickupTask,
        private FailPickupTaskHandler $failPickupTask,
    ) {}

    public function list(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return PickupTaskResource::collection($this->listPickupTasks->handle(new ListPickupTasksCommand($actor, $nodeId)))->resolve();
    }

    public function create(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        string $correlationId,
    ): array {
        return (new PickupTaskResource($this->createPickupTask->handle(new CreatePickupTaskCommand($actor, $nodeId, $consignmentId, $correlationId))))->resolve();
    }

    public function get(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): array {
        return (new PickupTaskResource($this->getPickupTask->handle(new GetPickupTaskCommand($actor, $nodeId, $id))))->resolve();
    }

    public function assign(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        string $driverId,
        int $expected,
        string $correlationId,
    ): array {
        return (new PickupTaskResource($this->assignPickupTask->handle(new AssignPickupTaskCommand($actor, $nodeId, $id, $driverId, $expected, $correlationId))))->resolve();
    }

    public function complete(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $correlationId,
    ): array {
        return (new PickupTaskResource($this->completePickupTask->handle(new CompletePickupTaskCommand($actor, $nodeId, $id, $expected, $correlationId))))->resolve();
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
        $this->failPickupTask->handle(new FailPickupTaskCommand($actor, $nodeId, $id, $expected, $reasonCode, $reason, $correlationId));
    }
}
