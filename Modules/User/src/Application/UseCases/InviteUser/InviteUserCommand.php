<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\InviteUser;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class InviteUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $channel,
        public string $correlationId,
    )
    {
    }
}
