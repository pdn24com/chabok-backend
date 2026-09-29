<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRoutePlan;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\MovementAccessGuardInterface;
use Modules\Operations\Application\Contracts\RoutePlanReaderInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

final readonly class GetRoutePlanHandler
{
    public function __construct(
        private MovementAccessGuardInterface $movementAccessGuard,
        private RoutePlanReaderInterface $routePlanReader,
    ) {}

    public function handle(GetRoutePlanCommand $command): RoutePlanRecord
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.view');
        $plan = $this->routePlanReader->find($actor->hqId, $id, $nodeId);
        if ($plan === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $plan;
    }
}
