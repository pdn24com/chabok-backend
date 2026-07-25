<?php

declare(strict_types=1);

namespace Modules\Identity\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class PasswordPolicy
{
    public function assertValid(string $password): void
    {
        $valid = strlen($password) >= 12
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);

        if (! $valid) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'The request is invalid.',
                ['password' => ['Use at least 12 characters with upper, lower, number, and symbol.']],
            );
        }
    }
}
