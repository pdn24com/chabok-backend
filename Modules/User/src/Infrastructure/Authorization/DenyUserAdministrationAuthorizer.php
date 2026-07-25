<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Authorization;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;

final class DenyUserAdministrationAuthorizer implements UserAdministrationAuthorizer
{
    public function assertCan(
        AuthenticatedPrincipal $actor,
        string $permission,
        string $hqId,
    ): void {
        throw new ApiException(
            ApiErrorCode::Forbidden,
            403,
            'Access denied.',
        );
    }
}
