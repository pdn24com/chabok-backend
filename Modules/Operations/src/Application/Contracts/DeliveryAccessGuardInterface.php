<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface DeliveryAccessGuardInterface
{
    public function access(AuthenticatedPrincipal $actor, string $nodeId, string $permission): void;

    public function executionAccess(AuthenticatedPrincipal $actor, string $nodeId, string $id): void;
}
