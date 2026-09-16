<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetNode;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetNodeHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Organization\Application\Services\NetworkProjection $networkProjection,
    )
    {
    }

    public function handle(GetNodeCommand $command): GetNodeResult
    {
        return new GetNodeResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.view');
        $this->networkAccessGuard->assertNodeScope($actor, 'network.node.view', $nodeId);
        $row = $this->network->node($hqId, $nodeId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->networkProjection->nodeResource($row);
    }
}
