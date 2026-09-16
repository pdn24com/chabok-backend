<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities;

use Modules\Foundation\Application\Data\Page;

final readonly class ListCatalogIdentitiesResult
{
    public function __construct(public Page $data)
    {
    }
}
