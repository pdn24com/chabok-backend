<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Dto;

use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;

/**
 * What the archive is asked for. The resource pair narrows the archive to the documents attached to one
 * record, which is how the documents tab of a customer file reads the same endpoint as the archive page.
 */
final readonly class DocumentListFiltersDto
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
        public ?DocumentStatus $status = null,
        public ?string $categoryId = null,
        /** Matched against the title and the reference number alike. */
        public ?string $search = null,
        public ?DocumentResourceType $resourceType = null,
        public ?string $resourceId = null,
    ) {}
}
