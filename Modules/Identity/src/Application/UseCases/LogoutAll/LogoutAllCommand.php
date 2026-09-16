<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\LogoutAll;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class LogoutAllCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public ?string $currentPassword,
        public ?string $verificationToken,
        public string $correlationId,
    )
    {
    }
}
