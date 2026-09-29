<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

/** A tenant's hierarchy, loaded once and traversed without additional queries. */
final readonly class AreaHierarchy
{
    /** @var array<string, list<string>> */
    private array $childrenByParent;

    /** @param iterable<AreaEdge> $edges */
    public function __construct(iterable $edges)
    {
        $children = [];
        foreach ($edges as $edge) {
            $children[$edge->parentAreaId][] = $edge->childAreaId;
        }
        $this->childrenByParent = $children;
    }

    /** @return list<string> */
    public function descendants(string $areaId): array
    {
        // Mark the root visited too: malformed legacy cycles cannot include it
        // in its own descendants or make traversal unbounded.
        $visited = [$areaId => true];
        $pending = [$areaId];
        $descendants = [];
        for ($index = 0; $index < count($pending); $index++) {
            foreach ($this->childrenByParent[$pending[$index]] ?? [] as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }
                $visited[$childId] = true;
                $descendants[] = $childId;
                $pending[] = $childId;
            }
        }

        return $descendants;
    }
}
