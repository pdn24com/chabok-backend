<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerExternalInvoice;

use Modules\CrmFinance\Infrastructure\Persistence\Models\ExternalInvoiceRecord;

/** The stored external invoice. */
final readonly class CreateCustomerExternalInvoiceResult
{
    public function __construct(
        public ExternalInvoiceRecord $invoice,
    ) {}
}
