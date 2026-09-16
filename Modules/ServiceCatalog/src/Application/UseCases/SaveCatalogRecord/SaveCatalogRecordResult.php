<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord;

final readonly class SaveCatalogRecordResult
{
    public function __construct(public array $data)
    {
    }
}
