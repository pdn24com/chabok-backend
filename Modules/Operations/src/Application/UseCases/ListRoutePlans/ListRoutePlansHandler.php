<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRoutePlans;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListRoutePlansHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\MovementAccessGuard $movementAccessGuard,
        private \Modules\Operations\Application\Services\RoutePlanReader $routePlanReader,
        private \Modules\Operations\Application\Repositories\MovementRepository $plans,
    )
    {
    }

    public function handle(ListRoutePlansCommand $command): ListRoutePlansResult
    {
        return new ListRoutePlansResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.view');
        return array_map(fn($plan): array => $this->routePlanReader->routePlanItem($plan), $this->plans->plansVisibleAtNode($actor->hqId, $nodeId));
    }
}
