<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Data;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class IdentityData
{
    public static function fingerprint(?string $value): ?string
    {
        return $value === null || $value === '' ? null : hash('sha256', $value);
    }

    public static function publicUser(array $user): array
    {
        $public = array_intersect_key($user, array_flip([
            'user_id',
            'hq_id',
            'username',
            'mobile',
            'email',
            'first_name',
            'last_name',
            'display_name',
            'status',
            'must_change_password',
        ]));
        if (array_key_exists('must_change_password', $public)) {
            $public['must_change_password'] = (bool) $public['must_change_password'];
        }
        return $public;
    }

    public static function iso(string $value): string
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    public static function authenticationRequired(): never
    {
        throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'Authentication required.');
    }
}
