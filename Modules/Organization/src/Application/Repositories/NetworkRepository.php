<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface NetworkRepository
{
    public function paginateAreas(string $hqId, array $filters, array $visibleIds): Page;

    public function paginateNodes(string $hqId, array $filters, array $visibleIds): Page;

    public function areaCodeExists(string $hqId, string $code): bool;

    public function lockArea(string $hqId, string $areaId): ?object;

    public function hasActiveNodes(string $hqId, string $areaId): bool;

    public function hasActiveChildren(string $hqId, string $areaId): bool;

    public function node(string $hqId, string $nodeId): ?object;

    public function nodeCodeExists(string $hqId, string $code): bool;

    public function lockNode(string $hqId, string $nodeId): ?object;

    public function areaWithParent(string $hqId, string $areaId): ?object;

    public function activeAreaExists(string $hqId, string $areaId): bool;

    public function activeCity(string $cityId): ?object;

    public function activeProvinceExists(string $provinceId): bool;

    public function removeParent(string $hqId, string $areaId): int;

    public function insertArea(array $attributes): void;

    public function updateArea(string $areaId, array $attributes): void;

    public function insertNode(array $attributes): void;

    public function updateNode(string $nodeId, array $attributes): void;

    public function insertHierarchy(array $attributes): void;

    public function areaIds(string $hqId): array;

    public function wouldCreateCycle(string $hqId, string $areaId, string $parentId): bool;

    public function descendantIds(string $hqId, string $areaId): array;

    public function lockAreas(string $hqId, array $areaIds): void;

    public function countAreas(string $hqId, array $areaIds): int;
}
