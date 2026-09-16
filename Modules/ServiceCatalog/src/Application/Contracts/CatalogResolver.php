<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

interface CatalogResolver
{
    public function resolve(string $resource, string $reference, ?string $hqId = null, bool $locking = false): array;

    public function relatedVersions(string $resource, string $reference): array;

    public function optionBound(string $offeringReference, string $optionReference, string $hqId): bool;

    public function assertQuoteCurrent(object $quote): void;
}
