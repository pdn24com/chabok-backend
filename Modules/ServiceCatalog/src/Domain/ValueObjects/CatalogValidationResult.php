<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

final readonly class CatalogValidationResult
{
    /** @param list<CatalogValidationIssue> $errors */
    public function __construct(public array $errors) {}

    public function valid(): bool
    {
        return $this->errors === [];
    }
}
