<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CatalogResourceDefinition
{
    public function map(string $resource): array
    {
        if ($resource === 'commitment-schedules') {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return \Modules\ServiceCatalog\Domain\CatalogResource::keys($resource);
    }
}
