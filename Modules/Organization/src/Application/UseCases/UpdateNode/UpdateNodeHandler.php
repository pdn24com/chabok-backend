<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateNode;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Contracts\NetworkChangeRecorderInterface;
use Modules\Organization\Application\Contracts\NetworkInputValidatorInterface;
use Modules\Organization\Application\Mappers\NetworkInput;
use Modules\Organization\Application\Mappers\NodeAttributes;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\Organization\Application\UseCases\GetNode\GetNodeCommand;
use Modules\Organization\Application\UseCases\GetNode\GetNodeHandler;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class UpdateNodeHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private NetworkInputValidatorInterface $networkInputValidator,
        private ClockInterface $clock,
        private GetNodeHandler $getNodeHandler,
        private NetworkChangeRecorderInterface $networkChangeRecorder,
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function handle(UpdateNodeCommand $command): NodeRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.manage');
        $this->networkAccessGuard->assertNodeScope($actor, 'network.node.manage', $nodeId);
        if ($input->areaId !== null) {
            $this->networkAccessGuard->assertAreaScope($actor, 'network.node.manage', $input->areaId);
        }

        return $this->connection->transaction(function () use ($actor, $hqId, $nodeId, $input, $correlationId): NodeRecord {
            $row = $this->nodeRepository->lockByTenant($hqId, $nodeId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ((int) $row->version !== $input->expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'organization.node_changed_since_loaded');
            }
            $merged = NetworkInput::mergeNode($row, $input);
            $this->networkInputValidator->validateNodeInput($hqId, $merged);
            $before = clone $row;
            $this->nodeRepository->apply($row, NodeAttributes::fromDetails($merged) + [
                'status' => $input->status?->value ?? $row->status,
                'version' => (int) $row->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $after = $this->getNodeHandler->handle(new GetNodeCommand($actor, $nodeId));
            $this->networkChangeRecorder->record($actor, 'network.node.updated', 'NODE', $nodeId, $correlationId, $before, $after);

            return $after;
        }, attempts: 3);
    }
}
