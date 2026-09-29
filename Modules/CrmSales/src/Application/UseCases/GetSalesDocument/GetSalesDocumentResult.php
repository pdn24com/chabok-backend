<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\GetSalesDocument;

use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;

/** One sales document. */
final readonly class GetSalesDocumentResult
{
    public function __construct(
        public SalesDocumentRecord $document,
    ) {}
}
