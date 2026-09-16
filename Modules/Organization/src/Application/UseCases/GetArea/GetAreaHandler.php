<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetArea;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetAreaHandler
{
    public function __construct(
        private \Modules\Organization\Application\Services\NetworkAccessGuard $networkAccessGuard,
        private \Modules\Organization\Application\Services\NetworkProjection $networkProjection,
    )
    {
    }

    public function handle(GetAreaCommand $command): GetAreaResult
    {
        return new GetAreaResult($this->execute($command->actor, $command->areaId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $areaId): array
    {
        $hqId = $this->networkAccessGuard->access($actor, 'network.area.view');
        $this->networkAccessGuard->assertAreaScope($actor, 'network.area.view', $areaId);
        return $this->networkProjection->areaResource($this->networkProjection->areaRow($hqId, $areaId));
    }
}
