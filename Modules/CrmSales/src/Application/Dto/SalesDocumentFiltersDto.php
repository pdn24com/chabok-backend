<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Dto;

use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;

/** The status filter reads the revision a document currently shows, not the ones it has outgrown. */
final readonly class SalesDocumentFiltersDto
{
    public function __construct(
        public ?string $customerId = null,
        public ?SalesDocumentStatus $status = null,
    ) {}
}
