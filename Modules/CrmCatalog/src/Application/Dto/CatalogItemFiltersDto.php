<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Dto;

use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;

/** The catalog table's filters; a null narrows nothing. */
final readonly class CatalogItemFiltersDto
{
    public function __construct(
        public ?CatalogItemKind $kind = null,
        public ?CatalogItemStatus $status = null,
        public ?string $categoryId = null,
        /** Matched against the code and the title. */
        public ?string $search = null,
    ) {}
}
