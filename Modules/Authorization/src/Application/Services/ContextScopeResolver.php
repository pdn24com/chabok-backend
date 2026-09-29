<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Authorization\Application\Contracts\ContextScopeResolverInterface;
use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class ContextScopeResolver implements ContextScopeResolverInterface
{
    public function __construct(
        private ScopeTopologyInterface $scopeTopology,
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function accessibleNodeIds(string $hqId, array $assignments): array
    {
        $hierarchy = null;
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
                    $hierarchy ??= new AreaHierarchy($this->scopeTopology->areaEdges($hqId));
                    $areaIds = [...$areaIds, ...$hierarchy->descendants($assignment->scope_id)];
                }
            }
        }

        return $this->nodeRepository->activeIdsInScopes($hqId, $areaIds, $nodeIds, $all);
    }

    public function descendantAreaIds(string $hqId, string $areaId): array
    {
        $hierarchy = new AreaHierarchy($this->scopeTopology->areaEdges($hqId));

        return $hierarchy->descendants($areaId);
    }
}
