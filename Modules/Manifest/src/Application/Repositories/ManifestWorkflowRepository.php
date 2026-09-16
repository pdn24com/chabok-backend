<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */

interface ManifestWorkflowRepository
{
    public function successfulSourceParcel(?string $hqId, ?string $sourceManifestId, ?string $parcelId): ?object;

    public function closedOutboundManifests(string $hq, string $node): array;

    public function receptionManifests(string $hq, string $node): array;

    public function hasTransitReception(?string $manifestId, string $node): bool;

    public function hasFinalReception(?string $manifestId): bool;

    public function updateManifest(?string $id, array $changes): void;

    public function manifest(?string $hqId, string $node, ?string $id): ?object;

    public function firstManifestParcel(?string $manifestId): ?object;

    public function lockProcessableRows(?string $hqId, ?string $manifestId): array;

    public function updateManifestParcel(?string $manifestParcelId, array $changes): void;

    public function lockRows(?string $hqId, ?string $manifestId): array;

    public function lockManifest(?string $hqId, string $node, ?string $id): ?object;

    public function userDisplayName(?string $submittedBy): ?string;
}
