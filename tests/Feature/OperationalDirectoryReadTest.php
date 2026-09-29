<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Services\ManifestContextReferences;
use Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversCommand;
use Modules\Operations\Application\UseCases\ListAvailableDrivers\ListAvailableDriversHandler;
use Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesCommand;
use Modules\Operations\Application\UseCases\ListAvailableVehicles\ListAvailableVehiclesHandler;
use Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesCommand;
use Modules\Operations\Application\UseCases\ListOperationalRoutes\ListOperationalRoutesHandler;
use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Presentation\Http\Resources\AvailableDriverResource;
use Modules\Operations\Presentation\Http\Resources\AvailableVehicleResource;
use Modules\Operations\Presentation\Http\Resources\OperationalRouteResource;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class OperationalDirectoryReadTest extends TestCase
{
    public function test_driver_capabilities_load_in_two_queries_for_one_or_forty_drivers(): void
    {
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from('driver-'.$index);
            RecordFixtureQuery::table('drivers')->insert(['driver_id' => $id, 'hq_id' => '245213294', 'home_node_id' => '25296341',
                'driver_code' => $id, 'display_name' => $id, 'operational_type' => 'MULTI', 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE']);
            foreach (['PICKUP', 'DELIVERY'] as $capability) {
                RecordFixtureQuery::table('driver_capabilities')->insert(['driver_capability_id' => \Tests\Support\FixtureId::from($id.'-'.$capability), 'hq_id' => '245213294', 'driver_id' => $id, 'capability' => $capability]);
            }
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $rows = $this->app->make(ListAvailableDriversHandler::class)->handle(new ListAvailableDriversCommand($this->actor(), '25296341', DriverCapability::Pickup));
                $data = AvailableDriverResource::collection($rows)->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $data);
            self::assertSame(['DELIVERY', 'PICKUP'], $data[0]['capabilities']);
            self::assertSame('MULTI', $data[0]['operational_type']);
            self::assertArrayNotHasKey('id', $data[0]);
        }
        self::assertSame([2, 2], $counts);
        RecordFixtureQuery::table('drivers')->where('driver_id', '184088311')->update(['availability_status' => 'ON_MISSION']);
        RecordFixtureQuery::table('drivers')->where('driver_id', '191946620')->update(['hq_id' => '227711138']);
        $rows = $this->app->make(ListAvailableDriversHandler::class)->handle(new ListAvailableDriversCommand($this->actor(), '25296341', DriverCapability::Delivery));
        self::assertCount(38, $rows);
    }

    public function test_routes_legs_and_nodes_load_in_four_queries_independent_of_route_count(): void
    {
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from('route-'.$index);
            RecordFixtureQuery::table('route_definitions')->insert(['route_definition_id' => $id, 'hq_id' => '245213294', 'route_code' => $id, 'route_title' => $id, 'status' => 'ACTIVE']);
            RecordFixtureQuery::table('route_definition_legs')->insert(['route_definition_leg_id' => \Tests\Support\FixtureId::from($id.'-leg'), 'route_definition_id' => $id, 'hq_id' => '245213294',
                'leg_order' => 1, 'origin_node_id' => '25296341', 'destination_node_id' => '190608731', 'status' => 'ACTIVE']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $rows = $this->app->make(ListOperationalRoutesHandler::class)->handle(new ListOperationalRoutesCommand($this->actor(), '25296341'));
                $data = OperationalRouteResource::collection($rows)->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $data);
            self::assertSame('25296341', $data[0]['legs'][0]['origin_node']['node_code']);
            self::assertSame('190608731', $data[0]['legs'][0]['destination_node']['node_id']);
        }
        self::assertSame([4, 4], $counts);
        RecordFixtureQuery::table('route_definition_legs')->where('route_definition_leg_id', '220147954')->update(['status' => 'INACTIVE']);
        $rows = $this->app->make(ListOperationalRoutesHandler::class)->handle(new ListOperationalRoutesCommand($this->actor(), '25296341'));
        self::assertCount(0, $rows->firstWhere('route_definition_id', '139437088')->legs);
    }

    public function test_available_vehicle_response_retains_public_fields_and_scope(): void
    {
        foreach (['39581922' => '245213294', '106329882' => '227711138'] as $id => $tenant) {
            RecordFixtureQuery::table('vehicles')->insert(['vehicle_id' => $id, 'hq_id' => $tenant, 'vehicle_code' => $id, 'registration_number' => $id,
                'plate_number' => $id, 'vehicle_type' => 'VAN', 'home_node_id' => '25296341', 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE']);
        }
        $rows = $this->app->make(ListAvailableVehiclesHandler::class)->handle(new ListAvailableVehiclesCommand($this->actor(), '25296341'));
        self::assertSame([[
            'vehicle_id' => '39581922', 'vehicle_code' => '39581922', 'registration_number' => '39581922', 'vehicle_type' => 'VAN',
            'home_node_id' => '25296341', 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE', 'version' => 1,
        ]], AvailableVehicleResource::collection($rows)->resolve());
    }

    public function test_manifest_driver_choices_eager_load_capabilities_with_native_enum_fields(): void
    {
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from('manifest-driver-'.$index);
            RecordFixtureQuery::table('drivers')->insert(['driver_id' => $id, 'hq_id' => '245213294', 'home_node_id' => '25296341', 'driver_code' => $id,
                'display_name' => $id, 'operational_type' => 'MULTI', 'status' => 'ACTIVE', 'availability_status' => 'ON_MISSION']);
            foreach (['LINEHAUL', 'PICKUP'] as $capability) {
                RecordFixtureQuery::table('driver_capabilities')->insert(['driver_capability_id' => \Tests\Support\FixtureId::from($id.'-'.$capability), 'hq_id' => '245213294', 'driver_id' => $id, 'capability' => $capability]);
            }
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $choices = $this->app->make(ManifestContextReferences::class)->drivers('245213294', ['25296341']);
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $choices);
            self::assertSame(['LINEHAUL', 'PICKUP'], $choices[0]['capabilities']);
            self::assertSame('ON_MISSION', $choices[0]['availability_status']);
            self::assertSame('ACTIVE', $choices[0]['status']);
        }
        self::assertSame([2, 2], $counts);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.directory_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('directory_read_test');
        foreach (['drivers', 'driver_capabilities', 'vehicles', 'route_definitions', 'route_definition_legs'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Organization/database/migrations/*_create_nodes.php'))[0])->up();
        foreach (['25296341', '190608731'] as $node) {
            RecordFixtureQuery::table('nodes')->insert(['node_id' => $node, 'hq_id' => '245213294', 'area_id' => '78192358', 'node_code' => $node, 'node_title' => $node, 'node_type' => 'BRANCH']);
        }
        $resolver = Mockery::mock(AccessContextResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(new AccessContextDto(
            hqId: '245213294', permissions: ['driver.view', 'live_operations.view'], moduleEntitlements: [new ModuleEntitlementDto('Driver', EntitlementStatus::ENABLED), new ModuleEntitlementDto('LiveOperations', EntitlementStatus::ENABLED)],
        ));
        $this->app->instance(AccessContextResolverInterface::class, $resolver);
        $scopes = Mockery::mock(ScopedAccessInterface::class);
        $scopes->shouldReceive('nodes')->andReturn(['25296341', '190608731']);
        $this->app->instance(ScopedAccessInterface::class, $scopes);
    }

    private function actor(): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal('5212567', 'session', '245213294', false);
    }
}
