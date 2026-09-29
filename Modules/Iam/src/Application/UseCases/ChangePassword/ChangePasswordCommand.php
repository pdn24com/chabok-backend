<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ChangePassword;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ChangePasswordCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $currentPassword,
        public string $newPassword,
        public string $correlationId,
    ) {}
}
