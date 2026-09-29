<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Foundation\Domain\ValueObjects\ScopeCoverage;

/** Resolves the scope of a specific permission, never the unrelated role union. */
final class ScopedAccess implements ScopedAccessInterface
{
    public function __construct(private readonly ScopeTopologyInterface $scopeTopology) {}

    /** @return list<PermissionScope> */
    public static function scopes(AccessContextDto $context, string $permission): array
    {
        return $context->scopesFor($permission);
    }

    public function areaIds(
        string $hqId,
        string $areaId,
        bool $descendants,
    ): array {
        if (! $descendants) {
            return [$areaId];
        }
        $hierarchy = new AreaHierarchy($this->scopeTopology->areaEdges($hqId));

        return [$areaId, ...$hierarchy->descendants($areaId)];
    }

    public function nodes(
        AccessContextDto $context,
        string $permission,
        bool $activeOnly = true,
    ): array {
        $hqId = $context->hqId;
        if ($hqId === null) {
            return [];
        }
        $nodes = [];
        $areas = [];
        $all = false;
        $hierarchy = null;
        foreach (self::scopes($context, $permission) as $scope) {
            if ($scope->type === ScopeType::TENANT) {
                $all = true;
            }
            if ($scope->type === ScopeType::NODE) {
                $nodes[] = $scope->id;
            }
            if ($scope->type === ScopeType::AREA) {
                $areas[] = $scope->id;
                if ($scope->includesDescendants) {
                    $hierarchy ??= new AreaHierarchy($this->scopeTopology->areaEdges($hqId));
                    $areas = [...$areas, ...$hierarchy->descendants($scope->id)];
                }
            }
        }
        if (! $all && $nodes === [] && $areas === []) {
            return [];
        }

        return $this->scopeTopology->nodeIds($hqId, $all, $nodes, $areas, $activeOnly);
    }

    public function coverage(string $hqId): ScopeCoverage
    {
        return new ScopeCoverage(
            new AreaHierarchy($this->scopeTopology->areaEdges($hqId)),
            $this->scopeTopology->nodeAreas($hqId),
        );
    }
}
