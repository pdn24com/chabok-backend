<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Authorization\Infrastructure\Adapters\AuthorizationUserAssignmentReader;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Foundation\Domain\ValueObjects\ScopeCoverage;
use Modules\Iam\Presentation\Http\Resources\UserAssignmentResource;
use Modules\Organization\Application\Mappers\NetworkInput;
use Modules\Organization\Application\UseCases\ListAreas\ListAreasCommand;
use Modules\Organization\Application\UseCases\ListAreas\ListAreasHandler;
use Modules\Organization\Application\UseCases\ListDescendantAreas\ListDescendantAreasCommand;
use Modules\Organization\Application\UseCases\ListDescendantAreas\ListDescendantAreasHandler;
use Modules\Organization\Infrastructure\Adapters\EloquentScopeTopology;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class RefactoredHierarchyAndAssignmentsTest extends TestCase
{
    public function test_hierarchy_is_tenant_scoped_and_eager_loads_public_identifiers(): void
    {
        RecordFixtureQuery::table('areas')->insert([
            ['id' => 1, 'hq_id' => '212432914', 'area_id' => '1', 'area_title' => 'Root'],
            ['id' => 2, 'hq_id' => '212432914', 'area_id' => '2', 'area_title' => 'Child'],
            ['id' => 3, 'hq_id' => '212432914', 'area_id' => '3', 'area_title' => 'Leaf'],
            ['id' => 4, 'hq_id' => '65158786', 'area_id' => '4', 'area_title' => 'Foreign root'],
            ['id' => 5, 'hq_id' => '65158786', 'area_id' => '5', 'area_title' => 'Foreign'],
        ]);
        RecordFixtureQuery::table('area_hierarchies')->insert([
            ['hq_id' => '212432914', 'parent_area_id' => 1, 'child_area_id' => 2],
            ['hq_id' => '212432914', 'parent_area_id' => 2, 'child_area_id' => 3],
            ['hq_id' => '65158786', 'parent_area_id' => 4, 'child_area_id' => 5],
        ]);
        $descendants = new ListDescendantAreasHandler(new EloquentScopeTopology);
        DB::enableQueryLog();
        self::assertSame(['2', '3'], $descendants->handle(new ListDescendantAreasCommand('212432914', '1')));
        self::assertCount(3, DB::getQueryLog());
        self::assertTrue(in_array('3', $descendants->handle(new ListDescendantAreasCommand('212432914', '1')), true));
        self::assertFalse(in_array('5', $descendants->handle(new ListDescendantAreasCommand('212432914', '1')), true));
    }

    public function test_assignment_titles_are_eager_loaded_without_cross_tenant_leaks(): void
    {
        RecordFixtureQuery::table('areas')->insert([
            ['area_id' => '78192358', 'hq_id' => '212432914', 'area_title' => 'Local area'],
            ['area_id' => '5', 'hq_id' => '65158786', 'area_title' => 'Private title'],
        ]);
        RecordFixtureQuery::table('nodes')->insert(['node_id' => '88468052', 'hq_id' => '212432914', 'node_title' => 'Local node']);
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ['assignment_id' => (string) $i, 'hq_id' => '212432914', 'user_id' => '5212567', 'role_id' => '78735577',
                'scope_type' => $i % 2 === 0 ? 'AREA' : 'NODE',
                'scope_id' => $i === 0 ? '5' : ($i % 2 === 0 ? '78192358' : '88468052'),
                'status' => 'ACTIVE', 'created_at' => '2026-09-23 12:00:00'];
        }
        RecordFixtureQuery::table('user_role_assignments')->insert($rows);
        DB::enableQueryLog();
        $assignments = (new AuthorizationUserAssignmentReader)->forUser('212432914', '5212567');
        self::assertCount(30, $assignments);
        self::assertCount(3, DB::getQueryLog());
        $payload = UserAssignmentResource::collection($assignments)->resolve(new Request);
        self::assertSame('ناحیه', $payload[0]['scope_title']);
        self::assertSame('Local node', $payload[1]['scope_title']);
        self::assertSame('Local area', $payload[2]['scope_title']);
        self::assertFalse($payload[0]['includes_descendants']);
        self::assertSame(['assignment_id', 'user_id', 'role_id', 'scope_type', 'scope_id', 'scope_title', 'includes_descendants', 'status'], array_keys($payload[0]));
    }

    public function test_network_reads_models_and_json_writes_round_trip_without_double_encoding(): void
    {
        NodeRecord::query()->forceCreate([
            'node_id' => '28404510', 'hq_id' => '212432914', 'node_title' => 'Node',
            'capabilities' => ['PICKUP', 'DELIVERY'], 'address_snapshot' => ['country_code' => 'IR'],
        ]);
        $node = NodeRecord::query()->where(['hq_id' => '212432914', 'node_id' => '28404510'])->first();
        self::assertSame(['PICKUP', 'DELIVERY'], $node->capabilities);
        self::assertSame(['country_code' => 'IR'], $node->address_snapshot);
        $node->forceFill(['capabilities' => ['PICKUP']])->save();
        self::assertSame(['PICKUP'], NodeRecord::query()->where(['hq_id' => '212432914', 'node_id' => '28404510'])->first()->capabilities);
        self::assertSame(['PICKUP'], json_decode(RecordFixtureQuery::table('nodes')->where('node_id', '28404510')->value('capabilities'), true));
        self::assertNull(NodeRecord::query()->where(['hq_id' => '65158786', 'node_id' => '28404510'])->first());
    }

    public function test_area_page_eager_loads_parent_edges_and_preserves_total_and_filtering(): void
    {
        RecordFixtureQuery::table('areas')->insert([
            ['area_id' => '1', 'hq_id' => '212432914', 'area_title' => 'Root'],
            ['area_id' => '2', 'hq_id' => '212432914', 'area_title' => 'Child'],
            ['area_id' => '240536385', 'hq_id' => '65158786', 'area_title' => 'Hidden'],
        ]);
        RecordFixtureQuery::table('area_hierarchies')->insert(['hq_id' => '212432914', 'parent_area_id' => 1, 'child_area_id' => 2]);
        $context = new AccessContextDto(hqId: '212432914', permissions: ['network.area.view'],
            permissionScopes: ['network.area.view' => [new PermissionScope(ScopeType::TENANT, null)]],
            moduleEntitlements: [new ModuleEntitlementDto('LiveOperations', EntitlementStatus::ENABLED)]);
        $resolver = Mockery::mock(AccessContextResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn($context);
        $scoped = Mockery::mock(ScopedAccessInterface::class);
        $scoped->shouldReceive('coverage')->with('212432914')->andReturn(new ScopeCoverage(new AreaHierarchy([]), []));
        $this->app->instance(AccessContextResolverInterface::class, $resolver);
        $this->app->instance(ScopedAccessInterface::class, $scoped);
        $handler = $this->app->make(ListAreasHandler::class);
        DB::enableQueryLog();
        $page = $handler->handle(new ListAreasCommand(new AuthenticatedPrincipal('84712523', 'session', '212432914', false), NetworkInput::filters(['search' => 'Child'])));
        self::assertSame(1, $page->total());
        self::assertSame(1, $page->items()[0]->parentEdge->parent_area_id);
        self::assertSame('1', $page->items()[0]->parentEdge->parent->area_id);
        self::assertCount(5, DB::getQueryLog());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        // These focused read tests need only their projection columns, not the
        // MySQL-only schema and triggers of unrelated operational workflows.
        Schema::create('area_hierarchies', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->unsignedInteger('parent_area_id');
            $table->unsignedInteger('child_area_id');
        });
        Schema::create('areas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('area_title');
            $table->string('area_code')->default('A');
            $table->string('status')->default('ACTIVE');
            $table->integer('97144633')->default(1);
        });
        Schema::create('nodes', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('node_title');
            $table->json('capabilities')->nullable();
            $table->json('address_snapshot')->nullable();
        });
        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('user_id');
            $table->string('role_id');
            $table->string('scope_type');
            $table->string('scope_id')->nullable();
            $table->boolean('includes_descendants')->default(false);
            $table->string('status');
            $table->timestamp('created_at');
        });
    }
}
