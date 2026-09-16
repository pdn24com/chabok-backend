<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ResetPassword;

final readonly class ResetPasswordCommand
{
    public function __construct(public string $verificationToken, public string $newPassword, public string $correlationId)
    {
    }
}
