<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions;

use Modules\Foundation\Application\Data\Page;

final readonly class ListPublishedCatalogVersionsResult
{
    public function __construct(public Page $data)
    {
    }
}
