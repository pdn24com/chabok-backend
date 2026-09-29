<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ListDocumentCategories;

use Illuminate\Database\Eloquent\Collection;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentCategoryRecord;

/** The whole category tree of a tenant, flat, parents before their children. */
final readonly class ListDocumentCategoriesResult
{
    /**
     * @param  Collection<int, DocumentCategoryRecord>  $categories
     */
    public function __construct(
        public Collection $categories,
    ) {}
}
