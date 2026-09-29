<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */
interface ManifestConsignmentAccessInterface
{
    public function consignment(?string $hqId, ?string $consignmentId): ?ConsignmentRecord;

    public function custodyEvents(string $hq, string $manifest): Collection;

    /** @param Collection<int, ParcelRecord> $parcels */
    public function saveTenantParcels(string $hqId, Collection $parcels): void;

    /** @param list<string> $consignmentIds @return array<string, string|null> */
    public function deliveryNodes(string $hqId, array $consignmentIds): array;

    public function lockAtPickupNode(
        ?string $hqId,
        string $consignmentId,
        string $node,
    ): ?ConsignmentRecord;

    public function updatePickupDriver(?string $consignmentId, array $changes): void;
}
