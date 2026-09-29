<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetArea;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

final readonly class GetAreaHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private AreaRepositoryInterface $areaRepository,
    ) {}

    public function handle(GetAreaCommand $command): AreaRecord
    {
        $actor = $command->actor;
        $areaId = $command->areaId;
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.view');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.area.view', $areaId);

        $area = $this->areaRepository->findWithParentEdge($hqId, $areaId);
        if ($area === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $area;
    }
}
