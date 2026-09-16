<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\RevokeUserSessions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RevokeUserSessionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $userId, public string $correlationId)
    {
    }
}
