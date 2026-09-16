<?php

declare(strict_types=1);

namespace Modules\Foundation\Application;

use Illuminate\Support\Facades\DB;

/** Resolves the scope of a specific permission, never the unrelated role union. */
final class ScopedAccess
{
    public static function scopes(array $context, string $permission): array
    {
        return ((array) ($context['permission_scopes'] ?? []))[$permission] ?? [];
    }

    public static function areaIds(string $hqId, string $areaId, bool $descendants): array
    {
        $seen = [$areaId => true];
        if ($descendants) {
            $edges = DB::table('area_hierarchies')->where('hq_id', $hqId)->get(['parent_area_id', 'child_area_id'])->groupBy('parent_area_id');
            $queue = [$areaId];
            while ($queue !== []) {
                foreach ($edges[array_pop($queue)] ?? [] as $edge) {
                    if (! isset($seen[$edge->child_area_id])) {
                        $seen[$edge->child_area_id] = true;
                        $queue[] = (string) $edge->child_area_id;
                    }
                }
            }
        }
        return array_keys($seen);
    }

    public static function nodes(array $context, string $permission, bool $activeOnly = true): array
    {
        $hqId = $context['hq_id'] ?? null;
        if ($hqId === null) return [];
        $nodes = []; $areas = []; $all = false;
        foreach (self::scopes($context, $permission) as $scope) {
            if ($scope['scope_type'] === 'TENANT') $all = true;
            if ($scope['scope_type'] === 'NODE') $nodes[] = $scope['scope_id'];
            if ($scope['scope_type'] === 'AREA') $areas = [...$areas, ...self::areaIds($hqId, $scope['scope_id'], $scope['includes_descendants'])];
        }
        if (! $all && $nodes === [] && $areas === []) return [];
        return DB::table('nodes')->where('hq_id', $hqId)->when($activeOnly, fn ($q) => $q->where('status', 'ACTIVE'))
            ->when(! $all, fn ($q) => $q->where(fn ($q) => $q->whereIn('node_id', $nodes)->orWhereIn('area_id', $areas)))
            ->orderBy('node_id')->pluck('node_id')->all();
    }

    /** Administrative coverage includes grouping areas with no current nodes. */
    public static function covers(array $scopes, string $hqId, string $type, ?string $id, bool $descendants = false): bool
    {
        foreach ($scopes as $scope) {
            if ($scope['scope_type'] === 'TENANT') return true;
            if ($scope['scope_type'] === $type && $scope['scope_id'] === $id
                && (! $descendants || $scope['includes_descendants'])) return true;
            if ($scope['scope_type'] !== 'AREA') continue;
            $areas = self::areaIds($hqId, $scope['scope_id'], $scope['includes_descendants']);
            if ($type === 'AREA' && $scope['includes_descendants'] && in_array($id, $areas, true)) return true;
            if ($type === 'NODE' && DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $id])->whereIn('area_id', $areas)->exists()) return true;
        }
        return false;
    }
}
