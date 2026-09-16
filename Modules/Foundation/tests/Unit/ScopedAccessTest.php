<?php

declare(strict_types=1);

namespace Modules\Foundation\Tests\Unit;

use Modules\Foundation\Application\Contracts\ScopeTopology;
use Modules\Foundation\Application\ScopedAccess;
use PHPUnit\Framework\TestCase;

final class ScopedAccessTest extends TestCase
{
    public function test_cycles_are_bounded_and_only_the_requested_permissions_scopes_are_used(): void
    {
        $topology = $this->createMock(ScopeTopology::class);
        $topology->expects(self::once())->method('areaEdges')->with('tenant-a')->willReturn([
            (object) ['parent_area_id' => 'a', 'child_area_id' => 'b'],
            (object) ['parent_area_id' => 'b', 'child_area_id' => 'a'],
        ]);
        $topology->expects(self::once())->method('nodeIds')->with('tenant-a', false, [], ['a', 'b'], false)->willReturn(['n']);
        $access = new ScopedAccess($topology);
        self::assertSame(['n'], $access->nodes([
            'hq_id' => 'tenant-a',
            'permission_scopes' => [
                'view' => [['scope_type' => 'AREA', 'scope_id' => 'a', 'includes_descendants' => true]],
                'edit' => [['scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]],
            ],
        ], 'view', false));
    }

    public function test_missing_permission_never_queries_the_node_directory(): void
    {
        $topology = $this->createMock(ScopeTopology::class);
        $topology->expects(self::never())->method('nodeIds');
        self::assertSame([], (new ScopedAccess($topology))->nodes(['hq_id' => 'tenant-a'], 'edit'));
    }

    public function test_area_scope_does_not_grant_descendant_administration_without_the_flag(): void
    {
        $topology = $this->createMock(ScopeTopology::class);
        $topology->expects(self::never())->method('areaEdges');
        self::assertFalse((new ScopedAccess($topology))->covers([['scope_type' => 'AREA', 'scope_id' => 'a', 'includes_descendants' => false]], 'tenant-a', 'AREA', 'a', true));
    }
}
