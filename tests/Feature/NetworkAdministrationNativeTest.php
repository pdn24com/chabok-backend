<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Organization\Application\Mappers\NetworkInput;
use Modules\Organization\Application\Serialization\NetworkDocument;
use Modules\Organization\Application\UseCases\CreateArea\CreateAreaCommand;
use Modules\Organization\Application\UseCases\CreateArea\CreateAreaHandler;
use Modules\Organization\Application\UseCases\CreateNode\CreateNodeCommand;
use Modules\Organization\Application\UseCases\CreateNode\CreateNodeHandler;
use Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaCommand;
use Modules\Organization\Application\UseCases\UpdateArea\UpdateAreaHandler;
use Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeCommand;
use Modules\Organization\Application\UseCases\UpdateNode\UpdateNodeHandler;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;
use RuntimeException;
use Tests\TestCase;

final class NetworkAdministrationNativeTest extends TestCase
{
    private array $audits = [];

    private bool $failOutbox = false;

    private AuthenticatedPrincipal $actor;

    public function test_area_edits_keep_parent_omission_clearing_previous_audit_and_atomic_rollback(): void
    {
        $create = $this->app->make(CreateAreaHandler::class);
        $parent = $create->handle(new CreateAreaCommand($this->actor, NetworkInput::area(['area_code' => 'P', 'area_title' => 'Parent']), 'create-parent'));
        $child = $create->handle(new CreateAreaCommand($this->actor, NetworkInput::area(['area_code' => 'C', 'area_title' => 'Child', 'parent_area_id' => $parent->area_id]), 'create-child'));
        $update = $this->app->make(UpdateAreaHandler::class);
        $changed = $update->handle(new UpdateAreaCommand($this->actor, $child->area_id, NetworkInput::areaChanges(['expected_version' => 1, 'area_title' => 'Renamed']), 'update'));
        self::assertSame($parent->area_id, NetworkDocument::area($changed)['parent_area_id']);
        self::assertSame('Child', $this->audits[2]['before']['area_title']);
        self::assertSame(1, $this->audits[2]['before']['version']);
        self::assertSame('Renamed', $this->audits[2]['after']['area_title']);
        self::assertSame(2, $this->audits[2]['after']['version']);
        $this->failOutbox = true;
        try {
            $update->handle(new UpdateAreaCommand($this->actor, $child->area_id, NetworkInput::areaChanges(['expected_version' => 2, 'parent_area_id' => null]), '228742273'));
            self::fail('The outbox failure must abort both the model and hierarchy edit.');
        } catch (RuntimeException $error) {
            self::assertSame('Outbox failed', $error->getMessage());
        }
        $persisted = AreaRecord::query()->where('area_id', $child->area_id)->with('parentEdge.parent')->firstOrFail();
        self::assertSame(2, (int) $persisted->version);
        self::assertSame($parent->area_id, NetworkDocument::area($persisted)['parent_area_id']);
        $this->failOutbox = false;
        $cleared = $update->handle(new UpdateAreaCommand($this->actor, $child->area_id, NetworkInput::areaChanges(['expected_version' => 2, 'parent_area_id' => null]), 'clear'));
        self::assertNull(NetworkDocument::area($cleared)['parent_area_id']);
    }

    public function test_node_edits_keep_json_casts_old_audit_and_optimistic_version_checks(): void
    {
        $area = $this->app->make(CreateAreaHandler::class)->handle(new CreateAreaCommand($this->actor, NetworkInput::area(['area_code' => 'A', 'area_title' => 'Area']), '78192358'));
        $node = $this->app->make(CreateNodeHandler::class)->handle(new CreateNodeCommand($this->actor, NetworkInput::node([
            'node_code' => 'N', 'node_title' => 'Original', 'node_type' => 'HUB', 'area_id' => $area->area_id,
            'capabilities' => ['PICKUP', 'DELIVERY'], 'address' => ['country_code' => 'IR', 'line' => 'Old address', 'location' => ['latitude' => 35, 'longitude' => 51]],
        ]), '88468052'));
        $update = $this->app->make(UpdateNodeHandler::class);
        $changed = $update->handle(new UpdateNodeCommand($this->actor, $node->node_id, NetworkInput::nodeChanges([
            'expected_version' => 1, 'node_title' => 'Renamed', 'capabilities' => [],
        ]), 'update'));
        self::assertSame([], $changed->capabilities);
        self::assertSame(['PICKUP', 'DELIVERY'], $this->audits[2]['before']['capabilities']);
        self::assertSame('Original', $this->audits[2]['before']['node_title']);
        self::assertSame([], $this->audits[2]['after']['capabilities']);
        self::assertSame('Old address', NetworkDocument::node($changed)['address']['line']);
        self::assertSame(['latitude' => 35.0, 'longitude' => 51.0], NetworkDocument::node($changed)['address']['location']);
        try {
            $update->handle(new UpdateNodeCommand($this->actor, $node->node_id, NetworkInput::nodeChanges(['expected_version' => 1, 'status' => 'INACTIVE']), '168030777'));
            self::fail('Stale writes must fail.');
        } catch (ApiException $error) {
            self::assertSame(ApiErrorCode::VersionConflict, $error->errorCode);
        }
        self::assertSame('ACTIVE', NodeRecord::query()->where('node_id', $node->node_id)->value('status'));
        $changed = $update->handle(new UpdateNodeCommand($this->actor, $node->node_id, NetworkInput::nodeChanges(['expected_version' => 2, 'address' => ['country_code' => 'IR', 'line' => null, 'location' => null]]), 'clear'));
        self::assertNull(NetworkDocument::node($changed)['address']['location']);
        self::assertNull(NetworkDocument::node($changed)['address']['line']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.network_native' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('network_native');
        foreach (['areas', 'nodes', 'area_hierarchies'] as $table) {
            (require glob(base_path('Modules/Organization/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $permissions = ['network.area.view', 'network.area.manage', 'network.node.view', 'network.node.manage'];
        $scopes = array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]);
        $resolver = Mockery::mock(AccessContextResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(new AccessContextDto(hqId: '245213294', permissions: $permissions,
            permissionScopes: $scopes, moduleEntitlements: [new ModuleEntitlementDto('LiveOperations', EntitlementStatus::ENABLED)]));
        $this->app->instance(AccessContextResolverInterface::class, $resolver);
        $audit = Mockery::mock(AuditWriterInterface::class);
        $audit->shouldReceive('write')->andReturnUsing(function (...$args): string {
            $this->audits[] = ['action' => $args[2], 'before' => $args[6], 'after' => $args[7]];

            return '193065851';
        });
        $this->app->instance(AuditWriterInterface::class, $audit);
        $outbox = Mockery::mock(OutboxWriterInterface::class);
        $outbox->shouldReceive('write')->andReturnUsing(function (): string {
            if ($this->failOutbox) {
                throw new RuntimeException('Outbox failed');
            }

            return 'outbox';
        });
        $this->app->instance(OutboxWriterInterface::class, $outbox);
        $this->actor = new AuthenticatedPrincipal('84712523', 'session', '245213294', false);
    }
}
