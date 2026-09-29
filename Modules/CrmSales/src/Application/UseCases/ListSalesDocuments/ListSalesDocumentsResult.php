<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListSalesDocuments;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;

/** The sales documents of the tenant that match the filters. */
final readonly class ListSalesDocumentsResult
{
    /**
     * @param  Collection<int, SalesDocumentRecord>  $documents
     */
    public function __construct(
        public Collection $documents,
    ) {}
}
