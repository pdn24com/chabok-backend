<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\RevokeUserSessions;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RevokeUserSessionsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $correlationId,
    ) {}
}
