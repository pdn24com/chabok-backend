<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

interface PickupTaskGuardInterface
{
    public function version(PickupTaskRecord $task, int $expected): void;

    public function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, string $capability): void;
}
