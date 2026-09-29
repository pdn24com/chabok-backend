<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface NetworkAccessGuardInterface
{
    public function scopeAreas(AuthenticatedPrincipal $actor, string $permission): array;

    public function assertAreaScope(AuthenticatedPrincipal $actor, string $permission, ?string $areaId, bool $descendants = false): void;

    public function assertNodeScope(AuthenticatedPrincipal $actor, string $permission, string $nodeId): void;

    public function access(AuthenticatedPrincipal $actor, string $permission): string;
}
