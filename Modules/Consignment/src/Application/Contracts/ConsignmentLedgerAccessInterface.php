<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Dto\ParcelTransitionDto;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

/** Consignment-owned parcel state and immutable operational history. Caller owns the transaction. */
interface ConsignmentLedgerAccessInterface
{
    public function lockConsignment(?string $hqId, string $consignmentId): ?ConsignmentRecord;

    /** @return Collection<int, ParcelRecord> */
    public function lockParcels(?string $hqId, string $consignmentId): Collection;

    public function setDeliveryDriver(string $consignmentId, string $driverId): void;

    public function setDeliveryNode(
        string $consignmentId,
        string $nodeId,
        DateTimeImmutable $at,
    ): void;

    public function setPickupDriver(string $consignmentId, string $driverId): void;

    /** @param Collection<int, ParcelRecord> $parcels Locked snapshots; consignment lock is also required. */
    public function applyParcelTransition(string $hqId, string $consignmentId, Collection $parcels, ParcelTransitionDto $transition): void;

    public function setActiveRoute(
        string $hqId,
        string $consignmentId,
        string $planId,
        string $legId,
    ): void;
}
