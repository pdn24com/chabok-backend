<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\TransitionSalesDocumentVersion;

use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

/** The revision as it now stands, together with the document it belongs to. */
final readonly class TransitionSalesDocumentVersionResult
{
    public function __construct(
        public SalesDocumentRecord $document,
        public SalesDocumentVersionRecord $version,
    ) {}
}
