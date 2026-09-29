<?php

declare(strict_types=1);

namespace Modules\CrmSales\Infrastructure\Repositories;

use Modules\CrmSales\Application\Repositories\SalesDocumentVersionRepositoryInterface;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

final class EloquentSalesDocumentVersionRepository implements SalesDocumentVersionRepositoryInterface
{
    public function create(array $attributes): SalesDocumentVersionRecord
    {
        return SalesDocumentVersionRecord::query()->forceCreate($attributes);
    }

    public function lockForTenant(string $hqId, string $versionId): ?SalesDocumentVersionRecord
    {
        return SalesDocumentVersionRecord::query()
            ->where(['hq_id' => $hqId, 'sales_document_version_id' => $versionId])
            ->lockForUpdate()
            ->first();
    }

    public function findForTenant(string $hqId, string $versionId): ?SalesDocumentVersionRecord
    {
        return SalesDocumentVersionRecord::query()
            ->where(['hq_id' => $hqId, 'sales_document_version_id' => $versionId])
            ->first();
    }

    public function existsForCustomer(string $hqId, string $customerId, string $versionId): bool
    {
        return SalesDocumentVersionRecord::query()
            ->where(['hq_id' => $hqId, 'sales_document_version_id' => $versionId])
            ->whereHas('document', fn ($document) => $document->where(['hq_id' => $hqId, 'customer_id' => $customerId]))
            ->exists();
    }

    public function update(string $hqId, string $versionId, array $attributes): void
    {
        SalesDocumentVersionRecord::query()
            ->where(['hq_id' => $hqId, 'sales_document_version_id' => $versionId])
            ->update($attributes);
    }
}
