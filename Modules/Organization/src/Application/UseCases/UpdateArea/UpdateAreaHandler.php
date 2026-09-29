<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateArea;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Contracts\HierarchyEditorInterface;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Contracts\NetworkChangeRecorderInterface;
use Modules\Organization\Application\Repositories\AreaHierarchyRepositoryInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\Organization\Application\UseCases\GetArea\GetAreaCommand;
use Modules\Organization\Application\UseCases\GetArea\GetAreaHandler;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

final readonly class UpdateAreaHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private HierarchyEditorInterface $hierarchyEditor,
        private GetAreaHandler $getAreaHandler,
        private NetworkChangeRecorderInterface $networkChangeRecorder,
        private NodeRepositoryInterface $nodeRepository,
        private AreaRepositoryInterface $areaRepository,
        private AreaHierarchyRepositoryInterface $areaHierarchyRepository,
    ) {}

    public function handle(UpdateAreaCommand $command): AreaRecord
    {
        $actor = $command->actor;
        $areaId = $command->areaId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.manage');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.area.manage', $areaId, true);
        if ($input->parentSpecified) {
            $this->networkAccessGuard->assertAreaScope($actor, 'network.area.manage', $input->parentId, true);
        }

        return $this->connection->transaction(function () use ($actor, $hqId, $areaId, $input, $correlationId): AreaRecord {
            $row = $this->areaRepository->lockByTenant($hqId, $areaId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ((int) $row->version !== $input->expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'organization.area_changed_since_loaded');
            }
            $before = clone $row->load('parentEdge.parent');
            $status = $input->status?->value ?? $row->status;
            if ($status === 'INACTIVE') {
                $activeNodes = $this->nodeRepository->hasActiveInArea($hqId, $areaId);
                $activeChildren = $this->areaHierarchyRepository->hasActiveChildren($hqId, $areaId);
                if ($activeNodes || $activeChildren) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'organization.deactivate_dependent_nodes_child_areas_first');
                }
            }
            $this->areaRepository->apply($row, [
                'area_title' => $input->title ?? $row->area_title,
                'status' => $status,
                'version' => (int) $row->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            if ($input->parentSpecified) {
                $this->hierarchyEditor->replaceParent($hqId, $areaId, $input->parentId);
            }
            $after = $this->getAreaHandler->handle(new GetAreaCommand($actor, $areaId));
            $this->networkChangeRecorder->record($actor, 'network.area.updated', 'AREA', $areaId, $correlationId, $before, $after);

            return $after;
        }, attempts: 3);
    }
}
