<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

interface CatalogRecordRepository
{
    public function identity(string $hqId, string $resource, string $id): ?object;

    public function lockIdentity(string $hqId, string $resource, string $id): ?object;

    public function latestRevisionId(string $resource, string $id): ?string;

    public function identityForRevision(string $resource, string $reference): ?string;

    public function activeNode(string $hqId, string $nodeId): bool;

    public function latestRevision(string $resource, string $id): ?object;

    public function insertRevision(string $resource, array $attributes): void;

    public function supersedePublished(string $resource, string $id, \DateTimeInterface $at): void;

    public function updateRevision(string $resource, string $id, array $changes): void;

    public function updateIdentity(string $resource, string $id, array $changes): void;
}
