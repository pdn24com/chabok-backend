<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetDeliveryTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\DeliveryAccessGuard $deliveryAccessGuard,
        private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks,
        private \Modules\Operations\Application\Services\DeliveryTaskReader $deliveryTaskReader,
    )
    {
    }

    public function handle(GetDeliveryTaskCommand $command): GetDeliveryTaskResult
    {
        return new GetDeliveryTaskResult($this->execute($command->actor, $command->nodeId, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->deliveryAccessGuard->access($actor, $nodeId, 'live_operations.view');
        $row = $this->tasks->detail($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $detail = $this->deliveryTaskReader->summary($row);
        $detail['node'] = [
            'node_id' => (string) $row->node_id,
            'node_code' => (string) $row->node_code,
            'node_title' => (string) $row->node_title,
        ];
        $detail['driver'] = $row->assigned_driver_id === null ? null : [
            'driver_id' => (string) $row->assigned_driver_id,
            'driver_code' => (string) $row->driver_code,
            'display_name' => (string) $row->driver_name,
        ];
        $detail['parcels'] = $this->tasks->parcels($actor->hqId, $row->consignment_id);
        $detail['history'] = $this->tasks->history($id);
        $detail['last_mile_resolution'] = $this->deliveryTaskReader->resolution((string) $row->consignment_id);
        $detail['route_progress'] = $this->deliveryTaskReader->routeProgress((string) $row->consignment_id);
        $detail['permitted_actions'] = match ((string) $row->status) {
            'PENDING' => ['ASSIGN'],
            'ASSIGNED' => ['REASSIGN'],
            'IN_PROGRESS' => ['COMPLETE', 'FAIL'],
            'FAILED' => ['RETRY'],
            default => [],
        };
        return $detail;
    }
}
