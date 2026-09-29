<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\AreaEdge;

/**
 * Port owned by Foundation, implemented by the Organization module.
 *
 * @see Modules/Organization/src/Infrastructure/Adapters/EloquentScopeTopology.php (bound in OrganizationServiceProvider)
 */
interface ScopeTopologyInterface
{
    /** @return list<AreaEdge> */
    public function areaEdges(string $hqId): array;

    /** @param list<string> $nodeIds @param list<string> $areaIds @return list<string> */
    public function nodeIds(
        string $hqId,
        bool $all,
        array $nodeIds,
        array $areaIds,
        bool $activeOnly,
    ): array;

    /** @return array<string, string|null> */
    public function nodeAreas(string $hqId): array;
}
