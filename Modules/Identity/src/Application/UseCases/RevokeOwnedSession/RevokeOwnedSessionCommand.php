<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\RevokeOwnedSession;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RevokeOwnedSessionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $sessionId, public string $correlationId)
    {
    }
}
