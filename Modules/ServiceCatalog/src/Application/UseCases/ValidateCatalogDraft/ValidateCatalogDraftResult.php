<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft;

final readonly class ValidateCatalogDraftResult
{
    public function __construct(public array $data)
    {
    }
}
