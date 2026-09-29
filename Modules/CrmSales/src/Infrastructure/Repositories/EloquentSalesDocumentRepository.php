<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Application\Dto\SalesDocumentFiltersDto;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;

final class EloquentSalesDocumentRepository implements SalesDocumentRepositoryInterface
{
    public function historyForCustomer(string $hqId, string $customerId): Collection
    {
        return SalesDocumentRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            ->with(['currentVersion' => fn ($version) => $version->select(['id', 'status', 'total', 'currency', 'issued_at'])])
            ->orderByDesc('id')
            ->get(['id', 'document_no', 'document_type', 'current_version_id', 'created_at']);
    }

    public function listForTenant(string $hqId, SalesDocumentFiltersDto $filters): Collection
    {
        $query = SalesDocumentRecord::query()
            ->where('hq_id', $hqId)
            ->with([
                'customer' => fn ($customer) => $customer->select(['id', 'display_name']),
                'currentVersion',
            ]);

        if ($filters->customerId !== null) {
            $query->where('customer_id', $filters->customerId);
        }
        // A document without a revision has no status to match, so the filter drops it rather than
        // treating the missing revision as one that happens to disagree.
        if ($filters->status !== null) {
            $query->whereRelation('currentVersion', 'status', $filters->status->value);
        }

        return $query->orderByDesc('id')->get();
    }

    public function findForTenant(string $hqId, string $documentId): ?SalesDocumentRecord
    {
        return SalesDocumentRecord::query()
            ->where(['hq_id' => $hqId, 'sales_document_id' => $documentId])
            ->with([
                'customer' => fn ($customer) => $customer->select(['id', 'display_name', 'customer_code']),
                'opportunity' => fn ($opportunity) => $opportunity->select(['id', 'title']),
                'currentVersion',
                'versions',
            ])
            ->first();
    }

    public function lockForTenant(string $hqId, string $documentId): ?SalesDocumentRecord
    {
        return SalesDocumentRecord::query()
            ->where(['hq_id' => $hqId, 'sales_document_id' => $documentId])
            ->lockForUpdate()
            ->first();
    }

    public function create(array $attributes): SalesDocumentRecord
    {
        return SalesDocumentRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $documentId, array $attributes): void
    {
        SalesDocumentRecord::query()->where(['hq_id' => $hqId, 'sales_document_id' => $documentId])->update($attributes);
    }

    public function documentNoTaken(string $hqId, string $documentNo): bool
    {
        return SalesDocumentRecord::query()->where(['hq_id' => $hqId, 'document_no' => $documentNo])->exists();
    }

    public function lastSequenceForStem(string $hqId, string $stem): int
    {
        $last = SalesDocumentRecord::query()
            ->where('hq_id', $hqId)
            ->whereLike('document_no', $stem.'%')
            ->orderByDesc('document_no')
            ->value('document_no');

        return $last === null ? 0 : (int) substr((string) $last, strlen($stem));
    }
}
