<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetPickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetPickupTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\PickupAccessGuard $pickupAccessGuard,
        private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks,
        private \Modules\Operations\Application\Services\PickupTaskReader $pickupTaskReader,
    )
    {
    }

    public function handle(GetPickupTaskCommand $command): GetPickupTaskResult
    {
        return new GetPickupTaskResult($this->execute($command->actor, $command->nodeId, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->pickupAccessGuard->access($actor, $nodeId, 'pickup_request.view');
        $row = $this->tasks->find($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->pickupTaskReader->item($row);
    }
}
