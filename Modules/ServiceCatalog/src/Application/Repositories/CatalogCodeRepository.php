<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

interface CatalogCodeRepository
{
    public function lockOwner(string $owner): void;

    public function exists(string $resource, string $owner, string $code): bool;
}
