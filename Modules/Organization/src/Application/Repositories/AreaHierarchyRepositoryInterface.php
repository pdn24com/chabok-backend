<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Repositories;

interface AreaHierarchyRepositoryInterface
{
    public function deleteParentEdges(string $hqId, string $areaId): void;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): void;

    public function hasActiveChildren(string $hqId, string $areaId): bool;
}
