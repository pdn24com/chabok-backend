<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListCatalogPersonas;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogPersonaRecord;

/** The tenant's catalog personas, by display order. */
final readonly class ListCatalogPersonasResult
{
    /**
     * @param  Collection<int, CatalogPersonaRecord>  $personas
     */
    public function __construct(
        public Collection $personas,
    ) {}
}
