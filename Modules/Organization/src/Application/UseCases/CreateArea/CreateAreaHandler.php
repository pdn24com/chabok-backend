<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateArea;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Contracts\HierarchyEditorInterface;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Contracts\NetworkChangeRecorderInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Application\UseCases\GetArea\GetAreaCommand;
use Modules\Organization\Application\UseCases\GetArea\GetAreaHandler;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

final readonly class CreateAreaHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private HierarchyEditorInterface $hierarchyEditor,
        private GetAreaHandler $getAreaHandler,
        private NetworkChangeRecorderInterface $networkChangeRecorder,
        private AreaRepositoryInterface $areaRepository,
    ) {}

    public function handle(CreateAreaCommand $command): AreaRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.manage');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.area.manage', $input->parentId, true);

        return $this->connection->transaction(function () use ($actor, $hqId, $input, $correlationId): AreaRecord {
            if ($this->areaRepository->codeExists($hqId, $input->code)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'organization.area_code_already_exists');
            }
            $areaId = (string) $this->areaRepository->create([

                'hq_id' => $hqId,
                'area_code' => $input->code,
                'area_title' => $input->title,
                'status' => 'ACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->getKey();
            $this->hierarchyEditor->replaceParent($hqId, $areaId, $input->parentId);
            $after = $this->getAreaHandler->handle(new GetAreaCommand($actor, $areaId));
            $this->networkChangeRecorder->record($actor, 'network.area.created', 'AREA', $areaId, $correlationId, null, $after);

            return $after;
        }, attempts: 3);
    }
}
