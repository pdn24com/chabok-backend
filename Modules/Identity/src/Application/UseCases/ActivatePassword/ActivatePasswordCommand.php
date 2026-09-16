<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ActivatePassword;

final readonly class ActivatePasswordCommand
{
    public function __construct(
        public ?string $verificationToken,
        public ?string $invitationToken,
        public string $newPassword,
        public string $correlationId,
    )
    {
    }
}
