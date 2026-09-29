<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

interface ManifestTaskBatchWriterInterface
{
    /** @param list<ParcelRecord> $previousParcels Caller holds parent/parcel locks and owns the transaction. */
    public function apply(string $hqId, string $nodeId, string $target, ?string $driverId, array $previousParcels): void;
}
