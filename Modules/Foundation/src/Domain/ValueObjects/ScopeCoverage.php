<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

use Modules\Foundation\Domain\Enums\ScopeType;

/** An operation-local tenant snapshot: permission checks never issue queries. */
final readonly class ScopeCoverage
{
    /** @param array<string, string|null> $nodeAreas */
    public function __construct(private AreaHierarchy $hierarchy, private array $nodeAreas) {}

    /** @param list<PermissionScope> $scopes */
    public function covers(array $scopes, ScopeType $type, ?string $id, bool $descendants = false): bool
    {
        foreach ($scopes as $scope) {
            if ($scope->type === ScopeType::TENANT) {
                return true;
            }
            if ($scope->type === $type && $scope->id === $id && (! $descendants || $scope->includesDescendants)) {
                return true;
            }
            if ($scope->type !== ScopeType::AREA) {
                continue;
            }
            $areas = [$scope->id];
            if ($scope->includesDescendants) {
                $areas = [...$areas, ...$this->hierarchy->descendants($scope->id)];
            }
            if ($type === ScopeType::AREA && $scope->includesDescendants && in_array($id, $areas, true)) {
                return true;
            }
            if ($type === ScopeType::NODE && isset($this->nodeAreas[$id]) && in_array($this->nodeAreas[$id], $areas, true)) {
                return true;
            }
        }

        return false;
    }
}
