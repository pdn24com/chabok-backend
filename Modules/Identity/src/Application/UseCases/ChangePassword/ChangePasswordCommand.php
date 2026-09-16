<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ChangePassword;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $currentPassword,
        public string $newPassword,
        public string $correlationId,
    )
    {
    }
}
