<?php

declare(strict_types=1);

namespace Modules\Authorization\Domain\Policies;

use Modules\Authorization\Domain\Enums\AssignmentScopeFailure;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;

final class AssignmentScopePolicy
{
    public function validate(string $userId, PermissionScope $scope, bool $targetExists): ?AssignmentScopeFailure
    {
        return match ($scope->type) {
            ScopeType::TENANT => $scope->id === null && ! $scope->includesDescendants ? null : AssignmentScopeFailure::TenantTarget,
            ScopeType::SelfScope => $scope->id === $userId && ! $scope->includesDescendants ? null : AssignmentScopeFailure::SelfTarget,
            ScopeType::AREA => $scope->id !== null && $targetExists ? null : AssignmentScopeFailure::AreaTarget,
            ScopeType::NODE => $scope->id !== null && ! $scope->includesDescendants && $targetExists ? null : AssignmentScopeFailure::NodeTarget,
            ScopeType::VENDOR, ScopeType::VENDOR_BRANCH => AssignmentScopeFailure::UnsupportedTarget,
            ScopeType::PLATFORM => AssignmentScopeFailure::InvalidType,
        };
    }
}
