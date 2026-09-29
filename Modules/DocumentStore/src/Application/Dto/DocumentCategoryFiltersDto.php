<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Dto;

/** What the category tree is asked for. Null means both the usable and the retired categories. */
final readonly class DocumentCategoryFiltersDto
{
    public function __construct(public ?bool $active = null) {}
}
