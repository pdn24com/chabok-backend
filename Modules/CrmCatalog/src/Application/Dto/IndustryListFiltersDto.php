<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Dto;

final readonly class IndustryListFiltersDto
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
        public ?string $search = null,
        /** Null keeps both active and retired entries; the knowledge base retires rather than deletes. */
        public ?bool $isActive = null,
    ) {}
}
