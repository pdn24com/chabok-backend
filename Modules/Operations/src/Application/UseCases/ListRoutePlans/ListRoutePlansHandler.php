<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRoutePlans;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\MovementAccessGuardInterface;
use Modules\Operations\Application\Contracts\RoutePlanReaderInterface;

final readonly class ListRoutePlansHandler
{
    public function __construct(
        private MovementAccessGuardInterface $movementAccessGuard,
        private RoutePlanReaderInterface $routePlanReader,
    ) {}

    public function handle(ListRoutePlansCommand $command): Collection
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->movementAccessGuard->access($actor, $nodeId, 'live_operations.view');

        return $this->routePlanReader->visibleAtNode($actor->hqId, $nodeId);
    }
}
