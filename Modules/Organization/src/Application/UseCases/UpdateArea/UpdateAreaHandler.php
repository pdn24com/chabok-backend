<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateArea;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateAreaHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Organization\Application\Services\NetworkProjection $networkProjection,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Organization\Application\Services\HierarchyEditor $hierarchyEditor,
        private \Modules\Organization\Application\UseCases\GetArea\GetAreaHandler $getArea,
        private \Modules\Organization\Application\Services\NetworkChangeRecorder $networkChangeRecorder,
    )
    {
    }

    public function handle(UpdateAreaCommand $command): UpdateAreaResult
    {
        return new UpdateAreaResult($this->execute($command->actor, $command->areaId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $areaId, array $input, string $correlationId): array
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.manage');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.area.manage', $areaId, true);
        if (array_key_exists('parent_area_id', $input)) {
            $this->networkAccessGuard->assertAreaScope($actor, 'network.area.manage', $input['parent_area_id'], true);
        }
        return $this->transactions->run(function () use ($actor, $hqId, $areaId, $input, $correlationId): array {
            $row = $this->network->lockArea($hqId, $areaId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((int) $row->version !== (int) $input['expected_version']) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Area changed since it was loaded.');
            }
            $before = $this->networkProjection->areaResource($this->networkProjection->areaRow($hqId, $areaId));
            $status = (string) ($input['status'] ?? $row->status);
            if ($status === 'INACTIVE') {
                $activeNodes = $this->network->hasActiveNodes($hqId, $areaId);
                $activeChildren = $this->network->hasActiveChildren($hqId, $areaId);
                if ($activeNodes || $activeChildren) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Deactivate dependent Nodes and child Areas first.');
                }
            }
            $this->network->updateArea($areaId, [
                'area_title' => $input['area_title'] ?? $row->area_title,
                'status' => $status,
                'version' => (int) $row->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            if (array_key_exists('parent_area_id', $input)) {
                $this->hierarchyEditor->replaceParent($hqId, $areaId, $input['parent_area_id']);
            }
            $after = $this->getArea->handle(new \Modules\Organization\Application\UseCases\GetArea\GetAreaCommand($actor, $areaId))->data;
            $this->networkChangeRecorder->record($actor, 'network.area.updated', 'AREA', $areaId, $correlationId, $before, $after);
            return $after;
        });
    }
}
