<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateSalesDocument;

use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

/** The document as it now stands, together with the first revision it opened on. */
final readonly class CreateSalesDocumentResult
{
    public function __construct(
        public SalesDocumentRecord $document,
        public SalesDocumentVersionRecord $version,
    ) {}
}
