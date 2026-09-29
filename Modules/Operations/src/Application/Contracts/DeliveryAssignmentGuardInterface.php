<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

interface DeliveryAssignmentGuardInterface
{
    public function version(DeliveryTaskRecord $task, int $expected): void;

    public function eligibleDriver(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, ?string $currentTaskId = null): void;

    public function eligibleDriverForManifest(AuthenticatedPrincipal $actor, string $nodeId, string $driverId, string $manifestId, string $currentTaskId): void;
}
