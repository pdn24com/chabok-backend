<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateNode;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Contracts\NetworkChangeRecorderInterface;
use Modules\Organization\Application\Contracts\NetworkInputValidatorInterface;
use Modules\Organization\Application\Mappers\NodeAttributes;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\Organization\Application\UseCases\GetNode\GetNodeCommand;
use Modules\Organization\Application\UseCases\GetNode\GetNodeHandler;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class CreateNodeHandler
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

    public function handle(CreateNodeCommand $command): NodeRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.manage');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.node.manage', $input->details->areaId);

        return $this->connection->transaction(function () use ($actor, $hqId, $input, $correlationId): NodeRecord {
            $this->networkInputValidator->validateNodeInput($hqId, $input->details);
            if ($this->nodeRepository->codeExists($hqId, $input->code)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'organization.node_code_already_exists');
            }
            $nodeId = (string) $this->nodeRepository->create(NodeAttributes::fromDetails($input->details) + [

                'hq_id' => $hqId,
                'node_code' => $input->code,
                'status' => 'ACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->getKey();
            $after = $this->getNodeHandler->handle(new GetNodeCommand($actor, $nodeId));
            $this->networkChangeRecorder->record($actor, 'network.node.created', 'NODE', $nodeId, $correlationId, null, $after);

            return $after;
        }, attempts: 3);
    }
}
