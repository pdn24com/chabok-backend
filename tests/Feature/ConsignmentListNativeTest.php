<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Contracts\EditPricingImpactInterface;
use Modules\Consignment\Application\Dto\ConsignmentFiltersDto;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsCommand;
use Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups\CountConsignmentStatusGroupsHandler;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentCommand;
use Modules\Consignment\Application\UseCases\GetConsignment\GetConsignmentHandler;
use Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsCommand;
use Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions\GetConsignmentFilterOptionsHandler;
use Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsCommand;
use Modules\Consignment\Application\UseCases\ListConsignments\ListConsignmentsHandler;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentDetailResource;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentListResource;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ConsignmentListNativeTest extends TestCase
{
    public function test_list_serialization_has_constant_queries_and_latest_pricing_without_loading_all_versions(): void
    {
        $actor = new AuthenticatedPrincipal('5212567', 'session', '245213294', false);
        $counts = [];
        for ($i = 1; $i <= 40; $i++) {
            $this->seedConsignment(\Tests\Support\FixtureId::from('c'.$i));
            if (! in_array($i, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $page = $this->app->make(ListConsignmentsHandler::class)->handle(new ListConsignmentsCommand($actor, '88468052', ConsignmentFiltersDto::fromValidated(['page_size' => 50])));
                $rows = ConsignmentListResource::collection($page->getCollection())->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($i, $rows);
            self::assertSame($i, $page->total());
            self::assertSame(250, $rows[0]['payable_total_amount']);
            self::assertSame(1, $rows[0]['parcel_count']);
            self::assertSame('pickup driver', $rows[0]['pickup_man_title']);
            self::assertSame('delivery driver', $rows[0]['delivery_man_title']);
            self::assertSame('node title', $rows[0]['pickup_node_title']);
        }
        self::assertSame([7, 7], $counts);
        $filtered = $this->app->make(ListConsignmentsHandler::class)->handle(new ListConsignmentsCommand($actor, '88468052', ConsignmentFiltersDto::fromValidated(['search' => 'label-26291740', 'status_group' => 'NEW_ROUTED'])));
        self::assertSame(['26291740'], $filtered->pluck('consignment_id')->all());
        $counts = $this->app->make(CountConsignmentStatusGroupsHandler::class)->handle(new CountConsignmentStatusGroupsCommand($actor, '88468052', ConsignmentFiltersDto::fromValidated(['status' => ['OK'], 'status_group' => 'COMPLETED'])));
        self::assertSame(40, $counts->total);
        self::assertSame(40, $counts->newRouted);
        self::assertSame(40, $counts->assigned);
        $options = $this->app->make(GetConsignmentFilterOptionsHandler::class)->handle(new GetConsignmentFilterOptionsCommand($actor, '88468052'));
        self::assertSame(['32917576'], $options->pickupDrivers->pluck('driver_id')->all());
        self::assertSame(['189461406'], $options->deliveryDrivers->pluck('driver_id')->all());
    }

    public function test_visibility_includes_operational_contexts_and_excludes_foreign_tenant_and_empty_scope(): void
    {
        foreach (['32917576', '189461406', '89171103', '6862823', '47262741', '259303271', '240536385', '106329882'] as $id) {
            $this->seedConsignment($id, ['hq_id' => $id === '106329882' ? '106329882' : '245213294', 'pickup_node_id' => $id === '32917576' || $id === '106329882' ? '88468052' : '129087332', 'delivery_node_id' => $id === '189461406' ? '88468052' : '129087332']);
        }
        RecordFixtureQuery::table('parcels')->where('consignment_id', '89171103')->update(['current_node_id' => '88468052']);
        RecordFixtureQuery::table('route_plan_legs')->insert(['route_plan_leg_id' => '209489866', 'route_plan_id' => '105413112', 'source_route_definition_leg_id' => '69006970', 'hq_id' => '245213294', 'leg_order' => 1, 'origin_node_id' => '129087332', 'destination_node_id' => '88468052', 'status' => 'IN_TRANSIT']);
        RecordFixtureQuery::table('parcels')->where('consignment_id', '6862823')->update(['active_route_plan_leg_id' => '209489866']);
        RecordFixtureQuery::table('pickup_tasks')->insert(['pickup_task_id' => '242594078', 'hq_id' => '245213294', 'consignment_id' => '47262741', 'node_id' => '88468052', 'status' => 'PENDING']);
        RecordFixtureQuery::table('delivery_tasks')->insert(['delivery_task_id' => '267369911', 'hq_id' => '245213294', 'consignment_id' => '259303271', 'node_id' => '88468052', 'status' => 'IN_PROGRESS']);
        $query = $this->app->make(ConsignmentRepositoryInterface::class);
        self::assertEqualsCanonicalizing(['189461406', '259303271', '89171103', '32917576', '47262741', '6862823'], $query->visibleIds('245213294', ['88468052']));
        self::assertSame([], $query->visibleIds('245213294', []));
        RecordFixtureQuery::table('route_plan_legs')->where('route_plan_leg_id', '209489866')->update(['status' => 'RECEIVED']);
        RecordFixtureQuery::table('pickup_tasks')->where('pickup_task_id', '242594078')->update(['status' => 'COMPLETED']);
        RecordFixtureQuery::table('delivery_tasks')->where('delivery_task_id', '267369911')->update(['status' => 'COMPLETED']);
        self::assertEqualsCanonicalizing(['189461406', '89171103', '32917576'], $query->visibleIds('245213294', ['88468052']));
    }

    public function test_detail_batches_pricing_history_and_serializes_without_queries_or_permission_leaks(): void
    {
        foreach (['consignment_pricing_charge_lines', 'consignment_status_events', 'parcel_custody_events'] as $table) {
            (require glob(base_path('Modules/Consignment/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Operations/database/migrations/*_create_route_plans.php'))[0])->up();
        (require glob(base_path('Modules/Audit/database/migrations/*_create_audit_events.php'))[0])->up();
        foreach (['manifests', 'manifest_parcels'] as $table) {
            (require glob(base_path('Modules/Manifest/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $access = Mockery::mock(ConsignmentAccessGuardInterface::class);
        $access->shouldReceive('assertAccess')->andReturn(new AccessContextDto(hqId: '245213294', accessibleNodeIds: ['88468052'], actingNodeId: '88468052', permissions: ['parcel.view', 'audit.view', 'consignment.edit']));
        $this->app->instance(ConsignmentAccessGuardInterface::class, $access);
        $scopes = Mockery::mock(ScopedAccessInterface::class);
        $scopes->shouldReceive('nodes')->andReturn(['88468052']);
        $this->app->instance(ScopedAccessInterface::class, $scopes);
        $impact = Mockery::mock(EditPricingImpactInterface::class);
        $impact->shouldReceive('contactFields')->andReturn(['contact_name']);
        $this->app->instance(EditPricingImpactInterface::class, $impact);
        $this->seedConsignment('163586333');
        RecordFixtureQuery::table('consignment_pricing_versions')->delete();
        RecordFixtureQuery::table('audit_events')->insert(['audit_id' => '193065851', 'hq_id' => '245213294', 'action_key' => 'CREATED', 'target_type' => 'CONSIGNMENT', 'target_id' => '163586333', 'initiator_id' => '5212567', 'correlation_id' => '91733773']);
        $actor = new AuthenticatedPrincipal('5212567', 'session', '245213294', false);
        $counts = [];
        for ($i = 1; $i <= 40; $i++) {
            RecordFixtureQuery::table('consignment_pricing_versions')->insert(['hq_id' => '245213294', 'consignment_id' => '163586333', 'pricing_version_id' => \Tests\Support\FixtureId::from('price-'.$i), 'version_number' => $i, 'total_amount' => $i * 100, 'currency' => 'IRR']);
            RecordFixtureQuery::table('consignment_pricing_charge_lines')->insert(['hq_id' => '245213294', 'pricing_version_id' => \Tests\Support\FixtureId::from('price-'.$i), 'pricing_charge_line_id' => \Tests\Support\FixtureId::from('line-'.$i), 'line_number' => 1, 'charge_code' => 'FREIGHT', 'title' => 'Freight', 'amount' => $i * 100, 'explanation' => '[]']);
            RecordFixtureQuery::table('consignment_pricing_charge_lines')->insert(['hq_id' => '106329882', 'pricing_version_id' => \Tests\Support\FixtureId::from('price-'.$i), 'pricing_charge_line_id' => \Tests\Support\FixtureId::from('foreign-'.$i), 'line_number' => 2, 'charge_code' => 'FOREIGN', 'title' => 'Foreign', 'amount' => 999]);
            if (! in_array($i, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $detail = $this->app->make(GetConsignmentHandler::class)->handle(new GetConsignmentCommand($actor, '88468052', '163586333'));
                $counts[] = count(DB::connection()->getQueryLog());
                DB::connection()->flushQueryLog();
                $data = (new ConsignmentDetailResource($detail))->resolve();
                self::assertCount(0, DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($i, $data['accepted_pricing_versions']);
            self::assertSame($i * 100, $data['payable_total_amount']);
            self::assertCount(1, $data['accepted_pricing_versions'][0]['charge_lines']);
            self::assertSame([], $data['accepted_pricing_versions'][0]['charge_lines'][0]['explanation']);
            self::assertCount(1, $data['parcels']);
            self::assertCount(1, $data['audit_timeline']);
            self::assertArrayNotHasKey('id', $data);
        }
        self::assertSame($counts[0], $counts[1]);
        self::assertLessThanOrEqual(15, $counts[0]);
        $restrictedAccess = Mockery::mock(ConsignmentAccessGuardInterface::class);
        $restrictedAccess->shouldReceive('assertAccess')->andReturn(new AccessContextDto(hqId: '245213294', accessibleNodeIds: ['88468052'], actingNodeId: '88468052'));
        $this->app->instance(ConsignmentAccessGuardInterface::class, $restrictedAccess);
        $restricted = $this->app->make(GetConsignmentHandler::class)->handle(new GetConsignmentCommand($actor, '88468052', '163586333'));
        self::assertFalse($restricted->consignment->relationLoaded('auditEvents'));
        $data = (new ConsignmentDetailResource($restricted))->resolve();
        self::assertSame([], $data['parcels']);
        self::assertSame([], $data['audit_timeline']);
        self::assertSame([], $data['permitted_actions']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.consignment_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('consignment_read_test');
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['hq_id', 'consignment_number', 'pickup_node_id', 'delivery_node_id', 'pickup_man_id', 'delivery_man_id', 'receiver_contact_name', 'receiver_mobile', 'receiver_address_text', 'current_status', 'aggregate_mode', 'parcel_status_counts', 'created_at', 'updated_at', 'service_type_id', 'shipping_method_id'] as $column) {
                $table->string($column)->nullable();
            }
            $table->integer('version')->default(1);
        });
        Schema::create('consignment_pricing_versions', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['hq_id', 'consignment_id', 'currency'] as $column) {
                $table->string($column);
            }
            $table->integer('version_number');
            $table->integer('total_amount');
        });
        Schema::create('operational_statuses', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id')->nullable();
            $table->string('code');
            $table->string('status_group');
            $table->boolean('is_terminal')->default(false);
        });
        foreach (['pickup_tasks', 'delivery_tasks', 'route_plan_legs', 'drivers'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Consignment/database/migrations/*_create_parcels.php'))[0])->up();
        (require glob(base_path('Modules/Organization/database/migrations/*_create_nodes.php'))[0])->up();
        RecordFixtureQuery::table('operational_statuses')->insert(['hq_id' => null, 'code' => 'CFM', 'status_group' => 'NEW_ROUTED']);
        foreach (['88468052', '129087332'] as $id) {
            RecordFixtureQuery::table('nodes')->insert(['hq_id' => '245213294', 'node_id' => $id, 'area_id' => '78192358', 'node_code' => $id, 'node_title' => $id === '88468052' ? 'node title' : 'elsewhere title', 'node_type' => 'HUB']);
        }
        foreach (['32917576', '189461406'] as $id) {
            RecordFixtureQuery::table('drivers')->insert(['hq_id' => '245213294', 'driver_id' => $id, 'driver_code' => $id, 'display_name' => $id === '32917576' ? 'pickup driver' : 'delivery driver', 'home_node_id' => '88468052', 'operational_type' => 'MULTI', 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE']);
        }
        $access = Mockery::mock(ConsignmentAccessGuardInterface::class);
        $access->shouldReceive('assertAccess')->andReturn(new AccessContextDto(hqId: '245213294', accessibleNodeIds: ['88468052'], actingNodeId: '88468052'));
        $this->app->instance(ConsignmentAccessGuardInterface::class, $access);
    }

    private function seedConsignment(string $id, array $changes = []): void
    {
        RecordFixtureQuery::table('consignments')->insert(array_replace(['hq_id' => '245213294', 'consignment_id' => $id, 'consignment_number' => $id,
            'pickup_node_id' => '88468052', 'delivery_node_id' => '129087332', 'pickup_man_id' => '32917576', 'delivery_man_id' => '189461406',
            'receiver_contact_name' => 'Receiver', 'receiver_mobile' => '09123456789', 'receiver_address_text' => 'Address', 'current_status' => 'CFM',
            'aggregate_mode' => 'FULL', 'parcel_status_counts' => '{"CFM":1}', 'created_at' => '2026-09-23 12:00:00', 'updated_at' => '2026-09-23 12:00:00'], $changes));
        RecordFixtureQuery::table('parcels')->insert(['hq_id' => $changes['hq_id'] ?? '245213294', 'consignment_id' => $id, 'parcel_id' => \Tests\Support\FixtureId::from('parcel-'.$id), 'parcel_number' => 'label-'.$id, 'current_status' => 'CFM']);
        foreach ([1 => 100, 2 => 250] as $version => $total) {
            RecordFixtureQuery::table('consignment_pricing_versions')->insert(['hq_id' => $changes['hq_id'] ?? '245213294', 'consignment_id' => $id, 'pricing_version_id' => \Tests\Support\FixtureId::from($id.'-'.$version), 'currency' => 'IRR', 'version_number' => $version, 'total_amount' => $total]);
        }
    }
}
