<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory;

final readonly class GetCatalogHistoryResult
{
    public function __construct(public array $data)
    {
    }
}
