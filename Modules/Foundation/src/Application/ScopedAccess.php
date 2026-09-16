<?php

declare(strict_types=1);

namespace Modules\Foundation\Application;

use Modules\Foundation\Application\Contracts\ScopeTopology;
/** Resolves the scope of a specific permission, never the unrelated role union. */

final class ScopedAccess
{
    public function __construct(private readonly ScopeTopology $topology)
    {
    }

    public static function scopes(array $context, string $permission): array
    {
        return ((array) ($context['permission_scopes'] ?? []))[$permission] ?? [];
    }

    public function areaIds(string $hqId, string $areaId, bool $descendants): array
    {
        $seen = [$areaId => true];
        if ($descendants) {
            $edges = [];
            foreach ($this->topology->areaEdges($hqId) as $edge) {
                $edges[$edge->parent_area_id][] = $edge;
            }
            $queue = [$areaId];
            while ($queue !== []) {
                foreach ($edges[array_pop($queue)] ?? [] as $edge) {
                    if (!isset($seen[$edge->child_area_id])) {
                        $seen[$edge->child_area_id] = true;
                        $queue[] = (string) $edge->child_area_id;
                    }
                }
            }
        }
        return array_keys($seen);
    }

    public function nodes(array $context, string $permission, bool $activeOnly = true): array
    {
        $hqId = $context['hq_id'] ?? null;
        if ($hqId === null) {
            return [];
        }
        $nodes = [];
        $areas = [];
        $all = false;
        foreach (self::scopes($context, $permission) as $scope) {
            if ($scope['scope_type'] === 'TENANT') {
                $all = true;
            }
            if ($scope['scope_type'] === 'NODE') {
                $nodes[] = $scope['scope_id'];
            }
            if ($scope['scope_type'] === 'AREA') {
                $areas = [...$areas, ...$this->areaIds($hqId, $scope['scope_id'], $scope['includes_descendants'])];
            }
        }
        if (!$all && $nodes === [] && $areas === []) {
            return [];
        }
        return $this->topology->nodeIds($hqId, $all, $nodes, $areas, $activeOnly);
    }
    /** Administrative coverage includes grouping areas with no current nodes. */

    public function covers(array $scopes, string $hqId, string $type, ?string $id, bool $descendants = false): bool
    {
        foreach ($scopes as $scope) {
            if ($scope['scope_type'] === 'TENANT') {
                return true;
            }
            if ($scope['scope_type'] === $type && $scope['scope_id'] === $id && (!$descendants || $scope['includes_descendants'])) {
                return true;
            }
            if ($scope['scope_type'] !== 'AREA') {
                continue;
            }
            $areas = $this->areaIds($hqId, $scope['scope_id'], $scope['includes_descendants']);
            if ($type === 'AREA' && $scope['includes_descendants'] && in_array($id, $areas, true)) {
                return true;
            }
            if ($type === 'NODE' && $this->topology->nodeBelongsToAreas($hqId, $id, $areas)) {
                return true;
            }
        }
        return false;
    }
}
