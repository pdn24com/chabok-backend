<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final class IdentityDto
{
    public static function fingerprint(?string $value): ?string
    {
        return $value === null || $value === '' ? null : hash('sha256', $value);
    }

    public static function iso(string $value): string
    {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    public static function authenticationRequired(): never
    {
        throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'common.authentication_required');
    }
}
