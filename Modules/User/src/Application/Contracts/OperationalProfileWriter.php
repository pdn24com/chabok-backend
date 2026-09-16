<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface OperationalProfileWriter
{
    /** Returns an optional Node assignment to append inside the caller's transaction. */
    public function attach(AuthenticatedPrincipal $actor, string $userId, array $input, string $correlationId): ?array;
    public function forUser(AuthenticatedPrincipal $actor, string $userId): ?array;
}
