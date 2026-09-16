<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Operations\Domain\FleetWriteConflict;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class FleetFailure
{
    public function rethrowConflict(FleetWriteConflict $exception, string $message): never
    {
        throw new ApiException(ApiErrorCode::Conflict, 409, $message);
    }
}
