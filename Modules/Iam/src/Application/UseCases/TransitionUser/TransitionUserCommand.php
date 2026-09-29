<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\TransitionUser;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Domain\Enums\UserStatus;

final readonly class TransitionUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public UserStatus $to,
        public string $correlationId,
    ) {}
}
