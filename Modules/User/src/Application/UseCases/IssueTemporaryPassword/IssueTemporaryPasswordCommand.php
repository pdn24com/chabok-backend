<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\IssueTemporaryPassword;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class IssueTemporaryPasswordCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public string $password,
        public string $correlationId,
    )
    {
    }
}
