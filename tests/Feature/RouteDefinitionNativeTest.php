<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\RouteVersionChangesDto;
use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Application\Services\DestinationResolutionInput;
use Modules\Operations\Application\Services\RouteVersionGuard;
use Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsCommand;
use Modules\Operations\Application\UseCases\ListRouteVersions\ListRouteVersionsHandler;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\ResolveRouteDefinition\ResolveRouteDefinitionHandler;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Presentation\Http\Resources\RouteVersionResource;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class RouteDefinitionNativeTest extends TestCase
{
    public function test_history_eager_loads_legs_in_constant_queries_including_serialization(): void
    {
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $this->version(\Tests\Support\FixtureId::from('version-'.$index), $index);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $page = $this->app->make(ListRouteVersionsHandler::class)->handle(new ListRouteVersionsCommand(new AuthenticatedPrincipal('5212567', 'session', '245213294', false), '145247809', 1, 50));
                $data = RouteVersionResource::collection($page->getCollection())->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $data);
            self::assertSame(\Tests\Support\FixtureId::from('version-'.$index.'-leg'), $data[0]['legs'][0]['route_definition_leg_id']);
            self::assertSame($index, $data[0]['version_number']);
            self::assertArrayNotHasKey('id', $data[0]);
        }
        self::assertSame([4, 4], $counts);
    }

    public function test_resolution_keeps_publication_pointer_effective_boundaries_and_priority_ties(): void
    {
        $this->version('247878907', 1, ['status' => 'PUBLISHED', 'effective_from' => '2026-09-23 10:00:00', 'effective_to' => '2026-09-23 11:00:00']);
        $this->version('216841819', 2, ['status' => 'PUBLISHED', 'priority' => 999]);
        RecordFixtureQuery::table('route_definitions')->where('route_definition_id', '145247809')->update(['published_version_id' => '247878907']);
        $handler = $this->app->make(ResolveRouteDefinitionHandler::class);
        $command = new ResolveRouteDefinitionCommand('245213294', RoutePurpose::Trunk, '136369625', '108443836', null, CarbonImmutable::parse('2026-09-23 10:00:00'));
        $version = $handler->handle($command);
        self::assertSame('247878907', $version->route_definition_version_id);
        self::assertTrue($version->relationLoaded('legs'));
        self::assertSame(\Tests\Support\FixtureId::from('valid-leg'), (new RouteVersionResource($version))->resolve()['legs'][0]['route_definition_leg_id']);
        foreach ([['245213294', '2026-09-23 11:00:00'], ['106329882', '2026-09-23 10:00:00']] as [$tenant, $at]) {
            try {
                $handler->handle(new ResolveRouteDefinitionCommand($tenant, RoutePurpose::Trunk, '136369625', '108443836', null, CarbonImmutable::parse($at)));
                self::fail('No route should match.');
            } catch (ApiException $exception) {
                self::assertSame(ApiErrorCode::RouteNotFound, $exception->errorCode);
            }
        }
        RecordFixtureQuery::table('route_definitions')->insert(['route_definition_id' => '227711138', 'hq_id' => '245213294', 'route_code' => '227711138', 'route_title' => 'Other', 'published_version_id' => '116757996']);
        $this->version('116757996', 1, ['route_definition_id' => '227711138', 'status' => 'PUBLISHED']);
        try {
            $handler->handle($command);
            self::fail('Equal priorities must remain ambiguous.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::RouteAmbiguous, $exception->errorCode);
        }
    }

    public function test_validating_one_or_forty_legs_reads_nodes_once_and_rejects_foreign_nodes(): void
    {
        for ($index = 0; $index <= 40; $index++) {
            RecordFixtureQuery::table('nodes')->insert(['node_id' => \Tests\Support\FixtureId::from('n'.$index), 'hq_id' => '245213294', 'area_id' => '78192358', 'node_code' => \Tests\Support\FixtureId::from('n'.$index), 'node_title' => 'Node', 'node_type' => 'HUB']);
        }
        $legs = [];
        foreach (range(1, 40) as $index) {
            $legs[] = ['leg_order' => $index, 'origin_node_id' => \Tests\Support\FixtureId::from('n'.($index - 1)), 'destination_node_id' => \Tests\Support\FixtureId::from('n'.$index)];
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            $content = RouteVersionDto::fromValidated(['purpose' => 'TRUNK', 'origin_node_id' => '136369625', 'destination_node_id' => \Tests\Support\FixtureId::from('n'.$index), 'priority' => 0, 'legs' => $legs]);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $this->app->make(RouteVersionGuard::class)->validateContent('245213294', $content);
                self::assertCount(1, DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
        }
        RecordFixtureQuery::table('nodes')->where('node_id', '216602062')->update(['hq_id' => '106329882']);
        try {
            $this->app->make(RouteVersionGuard::class)->validateContent('245213294', $content);
            self::fail('Foreign route node must be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
        }
    }

    public function test_nullable_changes_distinguish_omitted_values_and_explicit_null(): void
    {
        $current = RouteVersionDto::fromValidated(['purpose' => 'TRUNK', 'origin_node_id' => '136369625', 'destination_node_id' => '108443836', 'priority' => 10,
            'offering_version_id' => '90771604', 'effective_from' => '2026-09-23', 'effective_to' => '2026-09-24', 'legs' => [['leg_order' => 1, 'origin_node_id' => '136369625', 'destination_node_id' => '108443836']]]);
        $unchanged = RouteVersionChangesDto::fromValidated(['expected_version' => 1, 'priority' => 0])->applyTo($current);
        self::assertSame(0, $unchanged->priority);
        self::assertSame('90771604', $unchanged->offeringVersionId);
        self::assertSame('2026-09-23', $unchanged->effectiveFrom);
        self::assertSame($current->legs, $unchanged->legs);
        $cleared = RouteVersionChangesDto::fromValidated(['expected_version' => 1, 'offering_version_id' => null, 'effective_from' => null])->applyTo($current);
        self::assertNull($cleared->offeringVersionId);
        self::assertNull($cleared->effectiveFrom);
        self::assertSame('2026-09-24', $cleared->effectiveTo);
    }

    public function test_destination_coordinates_retain_latitude_longitude_order_and_ignore_invalid_postal_code(): void
    {
        $consignment = (new ConsignmentRecord)->forceFill(['receiver_city_id' => null, 'receiver_postal_code' => 'invalid', 'receiver_latitude' => '35.6892', 'receiver_longitude' => '51.3890']);
        $location = ($this->app->make(DestinationResolutionInput::class))->destinationResolutionInput($consignment);
        self::assertSame(35.6892, $location->point->latitude);
        self::assertSame(51.3890, $location->point->longitude);
        self::assertNull($location->postalCode);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.route_native_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('route_native_test');
        foreach (['route_definitions', 'route_definition_versions', 'route_definition_version_legs'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Organization/database/migrations/*_create_nodes.php'))[0])->up();
        $resolver = Mockery::mock(AccessContextResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(new AccessContextDto(
            hqId: '245213294', permissions: ['network.route.view'], moduleEntitlements: [new ModuleEntitlementDto('LiveOperations', EntitlementStatus::ENABLED)],
        ));
        $this->app->instance(AccessContextResolverInterface::class, $resolver);
        RecordFixtureQuery::table('route_definitions')->insert(['route_definition_id' => '145247809', 'hq_id' => '245213294', 'route_code' => '145247809', 'route_title' => 'Route']);
    }

    private function version(string $id, int $number, array $attributes = []): void
    {
        RecordFixtureQuery::table('route_definition_versions')->insert([...[
            'route_definition_version_id' => $id, 'route_definition_id' => '145247809', 'hq_id' => '245213294', 'version_number' => $number,
            'status' => 'DRAFT', 'purpose' => 'TRUNK', 'origin_node_id' => '136369625', 'destination_node_id' => '108443836', 'priority' => 10,
        ], ...$attributes]);
        RecordFixtureQuery::table('route_definition_version_legs')->insert(['route_definition_version_leg_id' => \Tests\Support\FixtureId::from($id.'-leg'), 'route_definition_version_id' => $id, 'hq_id' => '245213294', 'leg_order' => 1, 'origin_node_id' => '136369625', 'destination_node_id' => '108443836']);
    }
}
