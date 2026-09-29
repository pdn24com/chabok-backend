<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Contracts\CanonicalGeographyResolverInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsCommand;
use Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsHandler;
use Modules\ServiceCatalog\Presentation\Http\Resources\ResolvedServiceOfferingResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AccessContexts;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class OfferingRuntimeBatchTest extends TestCase
{
    public static function commitments(): array
    {
        return ['legacy binding' => [false], 'distinct destination groups' => [true]];
    }

    #[DataProvider('commitments')]
    public function test_offering_list_loads_options_and_current_bound_schedules_in_constant_queries(bool $zonePolicy): void
    {
        config(['database.connections.offering_runtime' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('offering_runtime');
        foreach (['service_types', 'service_type_versions', 'shipping_methods', 'shipping_method_versions', 'service_offerings', 'service_offering_versions',
            'service_options', 'service_option_versions', 'service_offering_option_rules', 'service_eligibility_rules', 'service_coverage_references', 'service_availability_bindings',
            'service_offering_commitment_bindings', 'commitment_schedules', 'commitment_schedule_versions', 'commitment_schedule_scopes', 'commitment_schedule_windows'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        foreach (['pricing_zone_sets', 'pricing_zone_set_versions', 'pricing_zones', 'pricing_zone_members'] as $table) {
            (require glob(base_path('Modules/Pricing/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $authorization = Mockery::mock(AccessContextResolverInterface::class);
        $authorization->shouldReceive('resolve')->andReturn(AccessContexts::make([
            'hq_id' => '245213294', 'permissions' => ['service_catalog.resolve'], 'accessible_node_ids' => [],
            'module_entitlements' => [['module_code' => 'ServiceCatalog', 'status' => 'ENABLED']],
        ]));
        $geography = Mockery::mock(CanonicalGeographyResolverInterface::class);
        $geography->shouldReceive('canonicalizeContact')->andReturnUsing(static fn (array $contact): array => $contact);
        $this->app->instance(AccessContextResolverInterface::class, $authorization);
        $this->app->instance(CanonicalGeographyResolverInterface::class, $geography);
        foreach ([['service_types', 'service_type'], ['shipping_methods', 'shipping_method'], ['service_options', 'service_option']] as [$table, $key]) {
            RecordFixtureQuery::table($table)->insert([$key.'_id' => \Tests\Support\FixtureId::from($key), 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => $key, 'created_by' => '84712523']);
            foreach ([1 => 'SUPERSEDED', 2 => 'PUBLISHED'] as $number => $status) {
                RecordFixtureQuery::table($key.'_versions')->insert([$key.'_version_id' => \Tests\Support\FixtureId::from($key.'-v'.$number), $key.'_id' => \Tests\Support\FixtureId::from($key), 'hq_id' => '245213294',
                    'version_number' => $number, 'status' => $status, 'labels' => '{"fa":"جاری"}', 'definition' => '{}', 'created_by' => '84712523']);
            }
        }
        RecordFixtureQuery::table('commitment_schedules')->insert(['commitment_schedule_id' => '100081670', 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => 'SCHEDULE', 'title' => 'تعهد', 'created_by' => '84712523']);
        foreach ([1 => 'SUPERSEDED', 2 => 'PUBLISHED', 3 => 'DRAFT'] as $number => $status) {
            RecordFixtureQuery::table('commitment_schedule_versions')->insert(['commitment_schedule_version_id' => \Tests\Support\FixtureId::from('schedule-v'.$number), 'commitment_schedule_id' => '100081670',
                'hq_id' => '245213294', 'version_number' => $number, 'status' => $status, 'created_by' => '84712523']);
        }
        RecordFixtureQuery::table('commitment_schedule_scopes')->insert(['commitment_schedule_scope_id' => '99705290', 'hq_id' => '245213294', 'commitment_schedule_version_id' => '252026863', 'scope_type' => 'HQ']);
        $handler = $this->app->make(ResolveServiceOfferingsHandler::class);
        $command = new ResolveServiceOfferingsCommand(new AuthenticatedPrincipal('84712523', 'session', '245213294', false),
            OfferingSelectionInput::fromArray(['selected_option_version_ids' => ['238027382'], 'acceptance_at' => '2026-09-24T00:00:00Z', 'receiver' => ['city_id' => '18506435']]));
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from(sprintf('offering-%02d', $index));
            RecordFixtureQuery::table('service_offerings')->insert(['service_offering_id' => $id, 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => $id, 'created_by' => '84712523']);
            RecordFixtureQuery::table('service_offering_versions')->insert(['service_offering_version_id' => $id, 'service_offering_id' => $id,
                'hq_id' => '245213294', 'version_number' => 1, 'status' => 'PUBLISHED', 'labels' => '{}', 'sla_policy' => '{}',
                'service_type_version_id' => '53903261', 'shipping_method_version_id' => '36341595', 'created_by' => '84712523']);
            RecordFixtureQuery::table('service_availability_bindings')->insert(['availability_binding_id' => $id, 'service_offering_version_id' => $id, 'scope_type' => 'TENANT', 'scope_value' => '245213294']);
            RecordFixtureQuery::table('service_offering_option_rules')->insert(['offering_option_rule_id' => $id, 'service_offering_version_id' => $id, 'service_option_version_id' => '238027382', 'compatibility' => 'REQUIRED']);
            $boundVersion = '170367899';
            if ($zonePolicy) {
                $boundVersion = $this->destinationSchedule($id);
            }
            RecordFixtureQuery::table('service_offering_commitment_bindings')->insert(['offering_commitment_binding_id' => $id, 'service_offering_version_id' => $id,
                'commitment_schedule_version_id' => $boundVersion, 'pickup_mode' => 'NONE', 'delivery_mode' => 'COMPUTED',
                'duration_value' => 24, 'duration_unit' => 'HOUR', 'duration_anchor' => 'CONSIGNMENT_CREATED']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $results = ResolvedServiceOfferingResource::collection($handler->handle($command))->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $results);
            foreach ($results as $result) {
                self::assertSame(\Tests\Support\FixtureId::from('service_type-v2'), $result['service_type_version_id']);
                self::assertSame(\Tests\Support\FixtureId::from('shipping_method-v2'), $result['shipping_method_version_id']);
                self::assertSame(\Tests\Support\FixtureId::from('service_option-v2'), $result['options'][0]['service_option_version_id']);
                self::assertSame($zonePolicy ? \Tests\Support\FixtureId::from($result['service_offering_version_id'].'-schedule') : '252026863', $result['commitment']['schedule_version_id']);
                if ($zonePolicy) {
                    self::assertSame(\Tests\Support\FixtureId::from($result['service_offering_version_id'].'-zone-version'), $result['commitment']['destination_zone']['zone_set_version_id']);
                    self::assertSame('CITY', $result['commitment']['destination_zone']['zone']['code']);
                }
                self::assertSame('2026-09-25T00:00:00.000000Z', $result['commitment']['delivery']['computed_at']);
                self::assertSame('ELIGIBLE', $result['outcome']);
            }
        }
        self::assertSame($counts[0], $counts[1]);
        self::assertLessThanOrEqual($zonePolicy ? 29 : 25, $counts[1]);
        if ($zonePolicy) {
            // An inaccessible schedule must be skipped before its invalid zone group is consumed.
            RecordFixtureQuery::table('pricing_zone_sets')->where('pricing_zone_set_id', '228494006')->update(['hq_id' => '106329882']);
            RecordFixtureQuery::table('commitment_schedules')->where('commitment_schedule_id', '228494006')->update(['status' => 'INACTIVE']);
            self::assertCount(39, $handler->handle($command));
        }
        RecordFixtureQuery::table('commitment_schedules')->update(['status' => 'INACTIVE']);
        self::assertSame([], $handler->handle($command));
        RecordFixtureQuery::table('commitment_schedules')->update(['status' => 'ACTIVE', 'hq_id' => '106329882']);
        self::assertSame([], $handler->handle($command));
    }

    private function destinationSchedule(string $id): string
    {
        $delivery = ['mode' => 'COMPUTED', 'anchor' => 'CONSIGNMENT_CREATED', 'calculation' => 'ELAPSED', 'duration_value' => 24, 'duration_unit' => 'HOUR'];
        $policy = ['include_holidays' => true, 'pickup' => ['mode' => 'NONE'], 'delivery' => [...$delivery, 'duration_value' => 72],
            'zone_set_id' => $id, 'destination_rules' => [['id' => $id, 'destination_zone_code' => 'CITY', 'policy' => $delivery]]];
        RecordFixtureQuery::table('pricing_zone_sets')->insert(['pricing_zone_set_id' => $id, 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => $id, 'title' => $id, 'purpose' => 'SALES', 'created_by' => '84712523']);
        RecordFixtureQuery::table('pricing_zone_set_versions')->insert(['zone_set_version_id' => \Tests\Support\FixtureId::from($id.'-zone-version'), 'pricing_zone_set_id' => $id,
            'version_number' => 1, 'status' => 'PUBLISHED', 'valid_from' => '2020-01-01 00:00:00', 'created_by' => '84712523']);
        RecordFixtureQuery::table('pricing_zones')->insert(['pricing_zone_id' => $id, 'zone_set_version_id' => \Tests\Support\FixtureId::from($id.'-zone-version'), 'code' => 'CITY', 'title' => 'شهر']);
        RecordFixtureQuery::table('pricing_zone_members')->insert(['zone_member_id' => $id, 'pricing_zone_id' => $id, 'member_type' => 'CITY', 'city_id' => '18506435', 'reference_value' => '18506435', 'precedence' => 200]);
        RecordFixtureQuery::table('commitment_schedules')->insert(['commitment_schedule_id' => $id, 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => $id, 'title' => 'تعهد', 'created_by' => '84712523']);
        RecordFixtureQuery::table('commitment_schedule_versions')->insert(['commitment_schedule_version_id' => \Tests\Support\FixtureId::from($id.'-schedule'), 'commitment_schedule_id' => $id,
            'hq_id' => '245213294', 'version_number' => 1, 'status' => 'PUBLISHED', 'commitment_policy' => json_encode($policy), 'created_by' => '84712523']);
        RecordFixtureQuery::table('commitment_schedule_scopes')->insert(['commitment_schedule_scope_id' => $id, 'hq_id' => '245213294', 'commitment_schedule_version_id' => \Tests\Support\FixtureId::from($id.'-schedule'), 'scope_type' => 'HQ']);

        return \Tests\Support\FixtureId::from($id.'-schedule');
    }
}
