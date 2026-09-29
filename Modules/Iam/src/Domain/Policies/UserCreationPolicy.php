<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Policies;

use Modules\Iam\Domain\Enums\UserCreationMode;
use Modules\Iam\Domain\Exceptions\InvalidUserCreation;

final class UserCreationPolicy
{
    public function requireValidMode(?UserCreationMode $mode, bool $hasPassword, bool $hasMobile, bool $hasEmail): UserCreationMode
    {
        $valid = match ($mode) {
            UserCreationMode::DirectActive => $hasPassword,
            UserCreationMode::SmsInvitation => ! $hasPassword && $hasMobile,
            UserCreationMode::EmailInvitation => ! $hasPassword && $hasEmail,
            null => false,
        };
        if (! $valid) {
            throw new InvalidUserCreation;
        }

        return $mode;
    }
}
