<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetNode;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class GetNodeHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function handle(GetNodeCommand $command): NodeRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $hqId = $this->networkAccessGuard->access($actor, 'network.node.view');
        $this->networkAccessGuard->assertNodeScope($actor, 'network.node.view', $nodeId);
        $row = $this->nodeRepository->findByTenant($hqId, $nodeId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
