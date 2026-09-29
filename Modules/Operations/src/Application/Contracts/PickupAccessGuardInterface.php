<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface PickupAccessGuardInterface
{
    public function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void;

    public function accessExecution(AuthenticatedPrincipal $actor, string $nodeId, string $taskId): void;
}
