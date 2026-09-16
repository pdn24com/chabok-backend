<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */

interface ManifestConsignmentAccess
{
    public function consignment(?string $hqId, ?string $consignmentId): ?object;

    public function custodyEvents(string $hq, string $manifest): array;

    public function lockParcel(?string $hqId, ?string $parcelId): ?object;

    public function updateTenantParcel(?string $hqId, ?string $parcelId, array $changes): void;

    public function hasDeliveryNode(?string $hqId, ?string $consignmentId, string $node): bool;

    public function lockAtPickupNode(?string $hqId, string $consignmentId, string $node): ?object;

    public function appendCustodyEvent(array $attributes): void;

    public function updatePickupDriver(?string $consignmentId, array $changes): void;

    public function appendStatusEvent(array $attributes): void;

    public function parcel(string $hqId, string $parcelId): ?object;
}
