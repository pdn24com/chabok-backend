<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateNode;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateNodeHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Organization\Application\Services\NetworkInputValidator $networkInputValidator,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Organization\Application\Services\NetworkProjection $networkProjection,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Organization\Application\UseCases\GetNode\GetNodeHandler $getNode,
        private \Modules\Organization\Application\Services\NetworkChangeRecorder $networkChangeRecorder,
    )
    {
    }

    public function handle(CreateNodeCommand $command): CreateNodeResult
    {
        return new CreateNodeResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.manage');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.node.manage', $input['area_id']);
        return $this->transactions->run(function () use ($actor, $hqId, $input, $correlationId): array {
            $this->networkInputValidator->validateNodeInput($hqId, $input);
            if ($this->network->nodeCodeExists($hqId, $input['node_code'])) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'Node code already exists.');
            }
            $nodeId = $this->identifiers->uuid();
            $this->network->insertNode($this->networkProjection->nodeColumns($input) + [
                'node_id' => $nodeId,
                'hq_id' => $hqId,
                'node_code' => $input['node_code'],
                'status' => 'ACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $after = $this->getNode->handle(new \Modules\Organization\Application\UseCases\GetNode\GetNodeCommand($actor, $nodeId))->data;
            $this->networkChangeRecorder->record($actor, 'network.node.created', 'NODE', $nodeId, $correlationId, null, $after);
            return $after;
        });
    }
}
