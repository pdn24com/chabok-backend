<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface NetworkAccessGuardInterface
{
    public function assert(AuthenticatedPrincipal $actor, string $permission): string;
}
