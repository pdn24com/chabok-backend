<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Repositories;

use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;

interface SalesDocumentVersionRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): SalesDocumentVersionRecord;

    /** Reads the revision together with the document it belongs to; the caller must hold a transaction. */
    public function lockForTenant(string $hqId, string $versionId): ?SalesDocumentVersionRecord;

    public function findForTenant(string $hqId, string $versionId): ?SalesDocumentVersionRecord;

    /** True when the revision exists in the tenant and its document belongs to this customer. */
    public function existsForCustomer(string $hqId, string $customerId, string $versionId): bool;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $versionId, array $attributes): void;
}
