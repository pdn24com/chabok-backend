<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface NodeAccessValidator
{
    public function assertAccessible(AuthenticatedPrincipal $principal, string $nodeId): void;
}
