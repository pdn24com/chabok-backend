<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmSales\Application\Dto\SalesDocumentFiltersDto;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;

interface SalesDocumentRepositoryInterface
{
    /**
     * The sales documents of one customer, newest first, each with the content version it currently shows.
     *
     * @return Collection<int, SalesDocumentRecord>
     */
    public function historyForCustomer(string $hqId, string $customerId): Collection;

    /**
     * Every sales document of the tenant the filters let through, newest first, each with its customer
     * and the revision it currently shows.
     *
     * @return Collection<int, SalesDocumentRecord>
     */
    public function listForTenant(string $hqId, SalesDocumentFiltersDto $filters): Collection;

    /** One document with its customer, its opportunity and every revision it has been through. */
    public function findForTenant(string $hqId, string $documentId): ?SalesDocumentRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $documentId): ?SalesDocumentRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): SalesDocumentRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $documentId, array $attributes): void;

    public function documentNoTaken(string $hqId, string $documentNo): bool;

    /**
     * The highest counter already handed out under a numbering stem such as `PR-2026-`, or zero while
     * the tenant has none. The unique index stays the real guard against two writers picking the same.
     */
    public function lastSequenceForStem(string $hqId, string $stem): int;
}
