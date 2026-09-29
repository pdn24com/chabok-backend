<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\InviteUser;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class InviteUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $channel,
        public string $correlationId,
    ) {}
}
