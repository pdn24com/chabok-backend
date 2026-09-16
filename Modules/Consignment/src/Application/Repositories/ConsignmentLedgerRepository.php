<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

/** Consignment-owned parcel state and immutable operational history. Caller owns the transaction. */

interface ConsignmentLedgerRepository
{
    public function lockConsignment(?string $hqId, string $consignmentId): ?object;

    public function lockParcels(?string $hqId, string $consignmentId): array;

    public function parcelStatusCounts(?string $hqId, string $consignmentId): array;

    public function updateConsignment(?string $hqId, string $consignmentId, array $attributes): void;

    public function setDeliveryDriver(string $consignmentId, string $driverId): void;

    public function setDeliveryNode(string $consignmentId, string $nodeId, \DateTimeImmutable $at): void;

    public function setPickupDriver(string $consignmentId, string $driverId): void;

    public function updateParcel(string $parcelId, array $attributes): void;

    public function appendStatusEvent(array $attributes): void;

    public function appendCustodyEvent(array $attributes): void;

    public function nextStatusSequence(string $consignmentId, ?string $hqId = null): int;

    public function nextCustodySequence(string $consignmentId): int;

    public function lockAtPickupNode(string $hqId, string $consignmentId, string $nodeId): ?object;

    public function setActiveRoute(string $hqId, string $consignmentId, string $planId, string $legId): void;
}
