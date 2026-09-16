<?php

declare(strict_types=1);

namespace Modules\User\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final class UserAdministrationPolicy
{
    public function assertCreationMode(string $mode, array $input): void
    {
        $hasPassword = isset($input['temporary_password']);
        $valid = match ($mode) {
            'DIRECT_ACTIVE' => $hasPassword,
            'SMS_INVITATION' => !$hasPassword && !empty($input['mobile']),
            'EMAIL_INVITATION' => !$hasPassword && !empty($input['email']),
            default => false,
        };
        if (!$valid) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The creation mode requirements are not satisfied.');
        }
    }

    public function requireTenant(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        return $actor->hqId;
    }

    public function assertTenantUser(?array $user, string $hqId): void
    {
        if ($user === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($user['hq_id'] !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
    }
}
