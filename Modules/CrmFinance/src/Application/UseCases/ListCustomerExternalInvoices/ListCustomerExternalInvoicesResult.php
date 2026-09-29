<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\ListCustomerExternalInvoices;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Infrastructure\Persistence\Models\ExternalInvoiceRecord;

/** Every external invoice of one customer, newest first. */
final readonly class ListCustomerExternalInvoicesResult
{
    /**
     * @param  Collection<int, ExternalInvoiceRecord>  $invoices
     */
    public function __construct(
        public Collection $invoices,
    ) {}
}
