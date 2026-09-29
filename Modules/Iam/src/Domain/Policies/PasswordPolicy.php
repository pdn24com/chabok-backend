<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\Policies;

use Modules\Iam\Domain\Exceptions\WeakPassword;

final class PasswordPolicy
{
    public function assertValid(string $password): void
    {
        $valid = strlen($password) >= 12 && preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password) && preg_match('/[0-9]/', $password) && preg_match('/[^A-Za-z0-9]/', $password);
        if (! $valid) {
            throw new WeakPassword;
        }
    }
}
