<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

interface CatalogInputInterface
{
    public function versionColumns(string $resource, CatalogDraftDto $input): array;

    public function databaseTimestamp(mixed $value): ?string;
}
