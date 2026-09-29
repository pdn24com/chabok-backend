<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateSalesDocumentVersion;

use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

/** The document now showing its new revision, together with that revision. */
final readonly class CreateSalesDocumentVersionResult
{
    public function __construct(
        public SalesDocumentRecord $document,
        public SalesDocumentVersionRecord $version,
    ) {}
}
