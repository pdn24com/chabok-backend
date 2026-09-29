<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ManifestAccessGuardInterface
{
    public function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): AccessContextDto;

    public function assertDriverVisibility(AccessContextDto $context, string $target): void;

    public function reviewAccess(AuthenticatedPrincipal $actor, string $node): void;

    public function requirePermission(AuthenticatedPrincipal $actor, string $permission): void;
}
