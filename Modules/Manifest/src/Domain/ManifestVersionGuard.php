<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestVersionGuard
{
    public function version(object $m, int $expected): void
    {
        if ((int) $m->version !== $expected) {
            throw new ApiException(ApiErrorCode::ManifestVersionConflict, 409, 'The Manifest version is stale.', details: ['current_version' => (int) $m->version]);
        }
    }
}
