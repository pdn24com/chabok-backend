<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Authorization\Domain\Enums\AssignmentScopeFailure;
use Modules\Authorization\Domain\Policies\AssignmentScopePolicy;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssignmentScopePolicyTest extends TestCase
{
    public static function scopes(): array
    {
        return [
            [ScopeType::TENANT, null, false, false, null],
            [ScopeType::TENANT, '55182337', false, true, AssignmentScopeFailure::TenantTarget],
            [ScopeType::TENANT, null, true, true, AssignmentScopeFailure::TenantTarget],
            [ScopeType::SelfScope, '5212567', false, false, null],
            [ScopeType::SelfScope, '227711138', false, true, AssignmentScopeFailure::SelfTarget],
            [ScopeType::SelfScope, '5212567', true, true, AssignmentScopeFailure::SelfTarget],
            [ScopeType::AREA, '55182337', true, true, null],
            [ScopeType::AREA, '55182337', false, true, null],
            [ScopeType::AREA, '55182337', false, false, AssignmentScopeFailure::AreaTarget],
            [ScopeType::AREA, null, false, true, AssignmentScopeFailure::AreaTarget],
            [ScopeType::NODE, '55182337', false, true, null],
            [ScopeType::NODE, '55182337', true, true, AssignmentScopeFailure::NodeTarget],
            [ScopeType::NODE, '55182337', false, false, AssignmentScopeFailure::NodeTarget],
            [ScopeType::NODE, null, false, true, AssignmentScopeFailure::NodeTarget],
            [ScopeType::VENDOR, '55182337', false, true, AssignmentScopeFailure::UnsupportedTarget],
            [ScopeType::VENDOR_BRANCH, '55182337', false, true, AssignmentScopeFailure::UnsupportedTarget],
            [ScopeType::PLATFORM, null, false, true, AssignmentScopeFailure::InvalidType],
        ];
    }

    #[DataProvider('scopes')]
    public function test_scope_shape_and_target_availability_are_independent_rules(ScopeType $type, ?string $id, bool $descendants, bool $exists, ?AssignmentScopeFailure $expected): void
    {
        self::assertSame($expected, (new AssignmentScopePolicy)->validate('5212567', new PermissionScope($type, $id, $descendants), $exists));
    }
}
