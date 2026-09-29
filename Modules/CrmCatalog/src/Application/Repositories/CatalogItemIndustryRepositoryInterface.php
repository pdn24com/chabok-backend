<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Repositories;

use DateTimeImmutable;

interface CatalogItemIndustryRepositoryInterface
{
    /** @return list<string> The industries the item is offered to, oldest link first. */
    public function industryIdsForItem(string $hqId, string $catalogItemId): array;

    /** @param list<string> $industryIds */
    public function attach(string $hqId, string $catalogItemId, array $industryIds, string $createdBy, DateTimeImmutable $at): void;

    /** @param list<string> $industryIds */
    public function detach(string $hqId, string $catalogItemId, array $industryIds): void;
}
