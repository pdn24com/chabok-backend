<?php

declare(strict_types=1);

namespace Modules\Organization\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AreaHierarchyService
{
    public function __construct(private TransactionManager $transactions) {}

    public function addEdge(string $hqId, string $parentAreaId, string $childAreaId): void
    {
        $this->transactions->run(function () use ($hqId, $parentAreaId, $childAreaId): void {
            DB::table('areas')->where('hq_id', $hqId)
                ->whereIn('area_id', [$parentAreaId, $childAreaId])
                ->lockForUpdate()->get();

            $count = DB::table('areas')->where('hq_id', $hqId)
                ->whereIn('area_id', [$parentAreaId, $childAreaId])->count();
            if ($count !== 2) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }

            $cycle = DB::selectOne(
                <<<'SQL'
                WITH RECURSIVE descendants AS (
                    SELECT child_area_id
                    FROM area_hierarchies
                    WHERE hq_id = ? AND parent_area_id = ?
                    UNION ALL
                    SELECT h.child_area_id
                    FROM area_hierarchies h
                    JOIN descendants d ON h.parent_area_id = d.child_area_id
                    WHERE h.hq_id = ?
                )
                SELECT EXISTS(
                    SELECT 1 FROM descendants WHERE child_area_id = ?
                ) AS creates_cycle
                SQL,
                [$hqId, $childAreaId, $hqId, $parentAreaId],
            );

            if ($parentAreaId === $childAreaId || (bool) ($cycle->creates_cycle ?? false)) {
                throw new ApiException(
                    ApiErrorCode::ValidationError,
                    422,
                    'Area hierarchy cycles are not allowed.',
                );
            }

            DB::table('area_hierarchies')->insert([
                'area_hierarchy_id' => (string) Str::uuid(),
                'hq_id' => $hqId,
                'parent_area_id' => $parentAreaId,
                'child_area_id' => $childAreaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** @return list<string> */
    public function descendantIds(string $hqId, string $areaId): array
    {
        return array_map(
            static fn ($row): string => (string) $row->area_id,
            DB::select(
                <<<'SQL'
                WITH RECURSIVE descendants AS (
                    SELECT child_area_id AS area_id
                    FROM area_hierarchies
                    WHERE hq_id = ? AND parent_area_id = ?
                    UNION ALL
                    SELECT h.child_area_id
                    FROM area_hierarchies h
                    JOIN descendants d ON h.parent_area_id = d.area_id
                    WHERE h.hq_id = ?
                )
                SELECT DISTINCT area_id FROM descendants
                SQL,
                [$hqId, $areaId, $hqId],
            ),
        );
    }
}
