<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

interface RoutePlanReaderInterface
{
    public function find(string $hqId, string $planId, string $nodeId): ?RoutePlanRecord;

    public function visibleAtNode(string $hqId, string $nodeId): Collection;
}
