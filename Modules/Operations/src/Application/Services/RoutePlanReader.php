<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Application\Contracts\RoutePlanReaderInterface;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

final class RoutePlanReader implements RoutePlanReaderInterface
{
    public function __construct(
        private RoutePlanRepositoryInterface $routePlanRepository,
    ) {}

    public function find(string $hqId, string $planId, string $nodeId): ?RoutePlanRecord
    {
        return $this->routePlanRepository->findVisibleAtNode($hqId, $nodeId, $planId);
    }

    public function visibleAtNode(string $hqId, string $nodeId): Collection
    {
        return $this->routePlanRepository->visibleAtNode($hqId, $nodeId);
    }
}
