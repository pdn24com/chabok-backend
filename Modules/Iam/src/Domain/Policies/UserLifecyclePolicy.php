<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Policies;

use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Domain\Exceptions\UserLifecycleViolation;

final class UserLifecyclePolicy
{
    public function assertTransition(UserStatus $from, UserStatus $to): void
    {
        // Activation of an invited user requires consuming Identity's activation proof.
        $allowed = match ($from) {
            UserStatus::Invited => [UserStatus::Deactivated],
            UserStatus::Active => [UserStatus::Suspended, UserStatus::Deactivated],
            UserStatus::Suspended => [UserStatus::Active, UserStatus::Deactivated],
            UserStatus::Deactivated => [UserStatus::Active],
        };
        if (! in_array($to, $allowed, true)) {
            throw new UserLifecycleViolation($from, $to);
        }
    }
}
