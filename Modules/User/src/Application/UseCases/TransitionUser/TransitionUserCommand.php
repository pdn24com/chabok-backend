<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\TransitionUser;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class TransitionUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $to,
        public string $correlationId,
    )
    {
    }
}
