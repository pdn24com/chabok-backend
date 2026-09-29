<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface OperationalDirectoryAccessInterface
{
    public function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission, string $module): void;
}
