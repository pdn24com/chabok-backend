<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive;

final readonly class SetCatalogRecordActiveResult
{
    public function __construct(public array $data)
    {
    }
}
