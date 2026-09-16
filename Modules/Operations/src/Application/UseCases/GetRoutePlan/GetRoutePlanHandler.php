<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRoutePlan;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetRoutePlanHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\MovementAccessGuard $movementAccessGuard,
        private \Modules\Operations\Application\Repositories\MovementRepository $plans,
        private \Modules\Operations\Application\Services\RoutePlanReader $routePlanReader,
    )
    {
    }

    public function handle(GetRoutePlanCommand $command): GetRoutePlanResult
    {
        return new GetRoutePlanResult($this->execute($command->actor, $command->nodeId, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.view');
        $plan = $this->plans->plan($actor->hqId, $id);
        if ($plan === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if (!$this->routePlanReader->planVisibleAtNode((string) $plan->route_plan_id, (string) $plan->consignment_id, $nodeId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->routePlanReader->routePlanItem($plan);
    }
}
