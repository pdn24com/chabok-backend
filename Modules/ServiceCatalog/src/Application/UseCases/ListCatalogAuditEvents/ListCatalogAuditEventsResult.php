<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents;

use Modules\Foundation\Application\Data\Page;

final readonly class ListCatalogAuditEventsResult
{
    public function __construct(public Page $data)
    {
    }
}
