<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Authorization;

use Modules\Foundation\Application\Contracts\NodeAccessValidator;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final class DenyNodeAccessValidator implements NodeAccessValidator
{
    public function assertAccessible(AuthenticatedPrincipal $principal, string $nodeId): void
    {
        throw new ApiException(ApiErrorCode::Forbidden, 403, 'Access denied.');
    }
}
