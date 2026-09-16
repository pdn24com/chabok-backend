<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

interface CatalogIdentityRepository
{
    public function current(string $resource, string $reference, ?string $hqId, bool $locking): ?object;

    public function relatedVersions(string $resource, string $reference): array;

    public function optionBound(string $offeringVersionId, array $optionVersionIds): bool;

    public function lockIdentityForRevision(string $resource, string $reference): void;

    public function revisionKey(string $resource): string;
}
