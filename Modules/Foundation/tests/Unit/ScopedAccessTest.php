<?php

declare(strict_types=1);

namespace Modules\Foundation\Tests\Unit;

use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Application\Services\ScopedAccess;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\AreaEdge;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Foundation\Domain\ValueObjects\ScopeCoverage;
use PHPUnit\Framework\TestCase;
use Tests\Support\AccessContexts;

final class ScopedAccessTest extends TestCase
{
    public function test_cycles_are_bounded_and_only_the_requested_permissions_scopes_are_used(): void
    {
        $topology = $this->createMock(ScopeTopologyInterface::class);
        $topology->expects(self::once())->method('areaEdges')->with('134901883')->willReturn([new AreaEdge('212432914', '65158786'), new AreaEdge('65158786', '212432914')]);
        $topology->expects(self::once())->method('nodeIds')->with('134901883', false, [], ['212432914', '65158786'], false)->willReturn(['28404510']);
        $access = new ScopedAccess($topology);
        self::assertSame(['28404510'], $access->nodes(AccessContexts::make(['hq_id' => '134901883', 'permission_scopes' => ['view' => [['scope_type' => 'AREA', 'scope_id' => '212432914', 'includes_descendants' => true]], 'edit' => [['scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]]]]), 'view', false));
    }

    public function test_missing_permission_never_queries_the_node_directory(): void
    {
        $topology = $this->createMock(ScopeTopologyInterface::class);
        $topology->expects(self::never())->method('nodeIds');
        self::assertSame([], (new ScopedAccess($topology))->nodes(AccessContexts::make(['hq_id' => '134901883']), 'edit'));
    }

    public function test_area_scope_does_not_grant_descendant_administration_without_the_flag(): void
    {
        $coverage = new ScopeCoverage(new AreaHierarchy([]), []);
        self::assertFalse($coverage->covers([new PermissionScope(ScopeType::AREA, '212432914')], ScopeType::AREA, '212432914', true));
    }

    public function test_coverage_loads_a_tenant_once_and_checks_many_nodes_without_queries(): void
    {
        $topology = $this->createMock(ScopeTopologyInterface::class);
        $topology->expects(self::once())->method('areaEdges')->with('134901883')->willReturn([new AreaEdge('parent', '232562279')]);
        $topology->expects(self::once())->method('nodeAreas')->with('134901883')->willReturn(['108443836' => 'parent', '4721300' => '232562279', 'outside' => '227711138']);
        $coverage = (new ScopedAccess($topology))->coverage('134901883');
        $scopes = [new PermissionScope(ScopeType::AREA, 'parent', true)];
        for ($i = 0; $i < 20; $i++) {
            self::assertTrue($coverage->covers($scopes, ScopeType::NODE, '108443836'));
            self::assertTrue($coverage->covers($scopes, ScopeType::NODE, '4721300'));
            self::assertFalse($coverage->covers($scopes, ScopeType::NODE, 'outside'));
            self::assertFalse($coverage->covers($scopes, ScopeType::NODE, 'foreign-tenant-node'));
        }
        self::assertFalse($coverage->covers([new PermissionScope(ScopeType::AREA, 'parent')], ScopeType::NODE, '4721300'));
    }

    public function test_self_scope_remains_limited_to_the_same_user(): void
    {
        $coverage = new ScopeCoverage(new AreaHierarchy([]), []);
        $scopes = ScopedAccess::scopes(AccessContexts::make(['permission_scopes' => ['view' => [['scope_type' => 'SELF', 'scope_id' => '264852120', 'includes_descendants' => false]]]]), 'view');
        self::assertTrue($coverage->covers($scopes, ScopeType::SelfScope, '264852120'));
        self::assertFalse($coverage->covers($scopes, ScopeType::SelfScope, 'user-b'));
        self::assertFalse($coverage->covers($scopes, ScopeType::TENANT, null));
    }
}
