<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

final readonly class ContextScopeResolver
{
    public function __construct(private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository)
    {
    }

    public function accessibleNodeIds(string $hqId, array $assignments): array
    {
        $all = false;
        $areaIds = [];
        $nodeIds = [];
        foreach ($assignments as $assignment) {
            if ($assignment->scope_type === 'TENANT') {
                $all = true;
            } elseif ($assignment->scope_type === 'NODE' && $assignment->scope_id !== null) {
                $nodeIds[] = (string) $assignment->scope_id;
            } elseif ($assignment->scope_type === 'AREA' && $assignment->scope_id !== null) {
                $areaIds[] = (string) $assignment->scope_id;
                if ((bool) $assignment->includes_descendants) {
                    $areaIds = [...$areaIds, ...$this->descendantAreaIds($hqId, (string) $assignment->scope_id)];
                }
            }
        }
        return $this->repository->scopedActiveNodeIds($hqId, $all, $areaIds, $nodeIds);
    }

    public function descendantAreaIds(string $hqId, string $areaId): array
    {
        return $this->repository->descendantAreaIds($hqId, $areaId);
    }
}
