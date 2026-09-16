<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion;

final readonly class TransitionCatalogVersionResult
{
    public function __construct(public array $data)
    {
    }
}
