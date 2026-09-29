<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;

final class UserAccessGuard implements UserAccessGuardInterface
{
    public function requireTenant(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }

        return $actor->hqId;
    }

    public function assertTenantUser(bool $exists, ?string $userHqId, string $hqId): void
    {
        if (! $exists) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        if ($userHqId !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
    }
}
