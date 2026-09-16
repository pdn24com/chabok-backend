<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateNode;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateNodeHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Organization\Application\Services\NetworkProjection $networkProjection,
        private \Modules\Organization\Application\Services\NetworkInputValidator $networkInputValidator,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Organization\Application\UseCases\GetNode\GetNodeHandler $getNode,
        private \Modules\Organization\Application\Services\NetworkChangeRecorder $networkChangeRecorder,
    )
    {
    }

    public function handle(UpdateNodeCommand $command): UpdateNodeResult
    {
        return new UpdateNodeResult($this->execute($command->actor, $command->nodeId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.manage');
        $this->networkAccessGuard->assertNodeScope($actor, 'network.node.manage', $nodeId);
        if (isset($input['area_id'])) {
            $this->networkAccessGuard->assertAreaScope($actor, 'network.node.manage', $input['area_id']);
        }
        return $this->transactions->run(function () use ($actor, $hqId, $nodeId, $input, $correlationId): array {
            $row = $this->network->lockNode($hqId, $nodeId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((int) $row->version !== (int) $input['expected_version']) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Node changed since it was loaded.');
            }
            $merged = [
                'area_id' => $input['area_id'] ?? $row->area_id,
                'node_title' => $input['node_title'] ?? $row->node_title,
                'node_type' => $input['node_type'] ?? $row->node_type,
                'capabilities' => $input['capabilities'] ?? json_decode((string) $row->capabilities, true, 512, JSON_THROW_ON_ERROR),
                'address' => $input['address'] ?? $this->networkProjection->addressResource($row),
            ];
            $this->networkInputValidator->validateNodeInput($hqId, $merged);
            $before = $this->networkProjection->nodeResource($row);
            $this->network->updateNode($nodeId, $this->networkProjection->nodeColumns($merged) + [
                'status' => $input['status'] ?? $row->status,
                'version' => (int) $row->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $after = $this->getNode->handle(new \Modules\Organization\Application\UseCases\GetNode\GetNodeCommand($actor, $nodeId))->data;
            $this->networkChangeRecorder->record($actor, 'network.node.updated', 'NODE', $nodeId, $correlationId, $before, $after);
            return $after;
        });
    }
}
