<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface FleetAccessGuardInterface
{
    public function access(AuthenticatedPrincipal $actor, string $permission): void;

    public function scopeNodes(AuthenticatedPrincipal $actor, string $permission): array;

    public function assertScopeNode(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void;

    public function activeNode(AuthenticatedPrincipal $actor, string $nodeId): void;

    public function availableUser(AuthenticatedPrincipal $actor, ?string $userId, ?string $currentDriverId = null): void;
}
