<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface ManifestRepository
{
    public function resolveInput(string $hqId, string $nodeId, string $identifier): array;

    public function list(string $hqId, string $nodeId, array $filters): Page;

    public function candidates(string $hqId, array $filters, array $scope): Page;

    public function batchCounts(string $hqId, array $manifestIds): array;

    public function hasParcels(string $id): bool;

    public function containsParcel(string $parcelId, string $id): bool;

    public function lockValidationRows(string $id): array;

    public function parcelDetails(string $id): array;

    public function statusEvents(string $hqId, string $manifestId): array;

    public function auditEvents(string $hqId, string $manifestId): array;

    public function find(string $hqId, string $nodeId, string $id): ?object;

    public function lock(string $hqId, string $nodeId, string $id): ?object;

    public function counts(string $id): array;

    public function insert(array $attributes): void;

    public function insertParcel(array $attributes): void;

    public function update(string $id, array $changes): void;

    public function updateParcel(string $id, array $changes): void;
}
