<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity;

final readonly class CreateCatalogIdentityResult
{
    public function __construct(public array $data)
    {
    }
}
