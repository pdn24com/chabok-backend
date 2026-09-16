<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\UpdateUser;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
