<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\CrmFinance\Infrastructure\Persistence\Models\ExternalInvoiceRecord;

final class EloquentExternalInvoiceRepository implements ExternalInvoiceRepositoryInterface
{
    public function listForCustomer(string $hqId, string $customerId): Collection
    {
        return $this->ofCustomer($hqId, $customerId)->orderByDesc('issued_on')->orderByDesc('id')->get();
    }

    public function create(array $attributes): ExternalInvoiceRecord
    {
        return ExternalInvoiceRecord::query()->forceCreate($attributes);
    }

    public function referenceTaken(string $hqId, string $externalSystem, string $referenceNo): bool
    {
        return ExternalInvoiceRecord::query()
            ->where(['hq_id' => $hqId, 'external_system' => $externalSystem, 'reference_no' => $referenceNo])
            ->exists();
    }

    public function belongsToCustomer(string $hqId, string $customerId, string $invoiceId): bool
    {
        return $this->ofCustomer($hqId, $customerId)->where('external_invoice_id', $invoiceId)->exists();
    }

    /** @return Builder<ExternalInvoiceRecord> */
    private function ofCustomer(string $hqId, string $customerId): Builder
    {
        return ExternalInvoiceRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId]);
    }
}
