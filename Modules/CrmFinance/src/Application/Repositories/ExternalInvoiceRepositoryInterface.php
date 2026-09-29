<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Infrastructure\Persistence\Models\ExternalInvoiceRecord;

interface ExternalInvoiceRepositoryInterface
{
    /**
     * Every external invoice of one customer, the most recently issued first.
     *
     * @return Collection<int, ExternalInvoiceRecord>
     */
    public function listForCustomer(string $hqId, string $customerId): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ExternalInvoiceRecord;

    /** True when the tenant already records this reference number under the same external system. */
    public function referenceTaken(string $hqId, string $externalSystem, string $referenceNo): bool;

    public function belongsToCustomer(string $hqId, string $customerId, string $invoiceId): bool;
}
