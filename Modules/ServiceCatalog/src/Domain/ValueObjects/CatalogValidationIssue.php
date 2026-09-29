<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Modules\ServiceCatalog\Domain\Enums\CatalogValidationCode;

final readonly class CatalogValidationIssue
{
    public function __construct(public CatalogValidationCode $code, public string $field) {}
}
