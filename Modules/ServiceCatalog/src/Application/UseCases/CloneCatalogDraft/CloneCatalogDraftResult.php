<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft;

final readonly class CloneCatalogDraftResult
{
    public function __construct(public array $data)
    {
    }
}
