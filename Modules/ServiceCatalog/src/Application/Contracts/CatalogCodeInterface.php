<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface CatalogCodeInterface
{
    public function generate(string $resource, string $owner): string;
}
