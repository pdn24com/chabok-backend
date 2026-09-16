<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft;

final readonly class UpdateCatalogDraftResult
{
    public function __construct(public array $data)
    {
    }
}
