<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Mappers\CoverageAddressInput;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Contracts\PolygonGeometryInterface;
use Modules\Geography\Domain\ValueObjects\Geometry;
use Modules\Pricing\Application\Serialization\ZoneDocument;
use Modules\Pricing\Application\Services\PricingZoneResolver;
use Modules\ServiceCatalog\Presentation\Http\Resources\CommitmentZoneGroupResource;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class PricingZoneNativeTest extends TestCase
{
    public function test_groups_batch_current_versions_and_zone_titles_and_keep_effective_tenant_rules(): void
    {
        $resolver = $this->app->make(PricingZoneResolver::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $group = \Tests\Support\FixtureId::from('group-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT));
            $this->group($group);
            RecordFixtureQuery::table('pricing_zone_sets')->where('pricing_zone_set_id', $group)->update(['code' => sprintf('group-%02d', $index), 'title' => sprintf('group-%02d', $index)]);
            $this->version($group, 1);
            $this->version($group, 2);
            $this->version($group, 3, 'DRAFT');
            $this->version($group, 4, 'PUBLISHED', '2026-09-25 00:00:00');
            $this->zone(\Tests\Support\FixtureId::from($group.'-old-zone'), \Tests\Support\FixtureId::from($group.'-1'), 'OLD');
            $this->zone(\Tests\Support\FixtureId::from($group.'-zone'), \Tests\Support\FixtureId::from($group.'-2'), 'CURRENT');
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            $result = $this->measure($counts, fn () => CommitmentZoneGroupResource::collection($resolver->groups('245213294'))->resolve());
            self::assertCount($index, $result);
            self::assertSame('156781520', $result[0]['zone_set_version_id']);
            self::assertSame([['code' => 'CURRENT', 'title' => 'CURRENT']], $result[0]['zones']);
            self::assertArrayNotHasKey('id', $result[0]);
        }
        self::assertSame([3, 3], $counts);
        $this->group('134224936', null);
        $this->version('134224936', 1);
        $this->group('106329882', '106329882');
        $this->version('106329882', 1);
        self::assertCount(41, $resolver->groups('245213294'));
        self::assertCount(2, $resolver->groups('106329882'));
        self::assertSame('156781520', $resolver->group('245213294', '54954258')->versionId);
        self::assertSame('156781520', $resolver->resolveEffectiveZoneSetVersion('166181494', CarbonImmutable::parse('2026-09-24T00:00:00Z')));
        RecordFixtureQuery::table('pricing_zone_set_versions')->where('zone_set_version_id', '156781520')->update(['valid_to' => '2026-09-24 00:00:00']);
        self::assertSame('166181494', $resolver->findEffectiveZoneSetVersion('156781520', CarbonImmutable::parse('2026-09-24T00:00:00Z')));
        self::assertNull($resolver->findEffectiveZoneSetVersion('missing', CarbonImmutable::parse('2026-09-24T00:00:00Z')));
        try {
            $resolver->group('245213294', '106329882');
            self::fail('Foreign zone groups must not resolve.');
        } catch (ApiException $error) {
            self::assertSame(422, $error->httpStatus);
        }
    }

    public function test_lane_shares_member_reads_and_preserves_precedence_ambiguity_and_version_isolation(): void
    {
        $resolver = $this->app->make(PricingZoneResolver::class);
        $this->zone('25296341', '97144633', 'ORIGIN');
        $this->zone('190608731', '97144633', 'DESTINATION');
        $this->zone('106329882', '128450447', 'FOREIGN');
        $this->member('182180242', '25296341', 'CITY', 'name', ['city_id' => '32209445']);
        $this->member('184873234', '190608731', 'CITY', 'name', ['city_id' => '63062457']);
        $this->member('249792217', '106329882', 'EXPLICIT_OVERRIDE', 'override');
        $counts = [];
        foreach ([1, 40] as $count) {
            for ($index = $count === 1 ? 1 : 2; $index <= $count; $index++) {
                $this->member(\Tests\Support\FixtureId::from('postal-'.$index), '25296341', 'POSTAL_RANGE', '1000000000', ['range_end' => '1999999999']);
            }
            $lane = $this->measure($counts, fn () => $resolver->resolveLane('97144633', CoverageAddressInput::fromArray(['city_id' => '32209445', 'zone_override' => 'override']), CoverageAddressInput::fromArray(['city_id' => '63062457'])));
            self::assertSame('ORIGIN', $lane->origin->zone->code);
            self::assertSame('DESTINATION', $lane->destination->zone->code);
            self::assertSame(200, $lane->origin->precedence);
            self::assertSame(['member_id' => '182180242', 'member_type' => 'CITY', 'precedence' => 200], ZoneDocument::match($lane->origin));
            self::assertArrayNotHasKey('id', ZoneDocument::zone($lane->origin));
        }
        self::assertSame([2, 2], $counts);
        $postal = $resolver->resolveZone('97144633', CoverageAddressInput::fromArray(['city_id' => '63062457', 'postal_code' => '1500000000']), 'receiver');
        self::assertSame('25296341', $postal->zone->pricing_zone_id);
        self::assertSame(300, $postal->precedence);
        $this->member('105384840', '190608731', 'POSTAL_RANGE', '1000000000', ['range_end' => '1999999999']);
        try {
            $resolver->resolveZone('97144633', CoverageAddressInput::fromArray(['postal_code' => '1500000000']), 'receiver');
            self::fail('Equal-precedence matches in different zones must be rejected.');
        } catch (ApiException $error) {
            self::assertSame('PRICING_ZONE_AMBIGUOUS', $error->details['reason_code']);
        }
    }

    public function test_native_polygon_cast_passes_typed_geometry_and_validated_coordinates_to_the_spatial_boundary(): void
    {
        $geometry = Mockery::mock(PolygonGeometryInterface::class);
        $geometry->shouldReceive('containsMany')->once()->with(Mockery::on(fn (array $geometries): bool => $geometries['45330818'] instanceof Geometry), 31.0, 51.0)->andReturn(['45330818' => true]);
        $this->app->instance(PolygonGeometryInterface::class, $geometry);
        $resolver = $this->app->make(PricingZoneResolver::class);
        $this->zone('137171062', '97144633', 'POLYGON');
        $this->member('45330818', '137171062', 'POLYGON', 'shape', ['geometry' => json_encode(['type' => 'Polygon', 'coordinates' => [[[50, 30], [52, 30], [52, 32], [50, 32], [50, 30]]]])]);
        $resolution = $resolver->resolveZone('97144633', CoverageAddressInput::fromArray(['latitude' => 31, 'longitude' => 51]), 'receiver');
        self::assertSame('137171062', $resolution->zone->pricing_zone_id);
        self::assertSame(250, $resolution->precedence);
        foreach ([['longitude' => 51], ['latitude' => 91, 'longitude' => 51]] as $invalid) {
            try {
                $resolver->resolveZone('97144633', CoverageAddressInput::fromArray($invalid), 'receiver');
                self::fail('Polygon matching requires valid coordinates.');
            } catch (ApiException $error) {
                self::assertSame('PRICING_COORDINATES_REQUIRED', $error->details['reason_code']);
                self::assertArrayHasKey('receiver.latitude', $error->fieldErrors);
            }
        }
    }

    public function test_destination_batch_has_constant_reads_and_defers_only_the_affected_group_errors(): void
    {
        $resolver = $this->app->make(PricingZoneResolver::class);
        $counts = [];
        $groups = [];
        for ($index = 1; $index <= 40; $index++) {
            $group = \Tests\Support\FixtureId::from('destination-'.$index);
            $groups[] = $group;
            $this->group($group);
            RecordFixtureQuery::table('pricing_zone_sets')->where('pricing_zone_set_id', $group)->update(['code' => sprintf('group-%02d', $index), 'title' => sprintf('group-%02d', $index)]);
            $this->version($group, 1);
            $this->version($group, 2);
            $this->version($group, 3, 'DRAFT');
            $this->version($group, 4, 'PUBLISHED', '2026-09-25 00:00:00');
            $this->zone($group, \Tests\Support\FixtureId::from($group.'-2'), 'CURRENT');
            $this->member($group, $group, 'CITY', '18506435', ['city_id' => '18506435']);
            if (in_array($index, [1, 40], true)) {
                $batch = $this->measure($counts, fn () => $resolver->destinations('245213294', $groups, CoverageAddressInput::fromArray(['city_id' => '18506435'])));
                foreach ($groups as $id) {
                    $destination = $batch->forGroup($id);
                    self::assertSame(\Tests\Support\FixtureId::from($id.'-2'), $destination->zoneSetVersionId);
                    self::assertSame('CURRENT', $destination->match->code);
                    self::assertSame(200, $destination->match->precedence);
                }
            }
        }
        self::assertSame([4, 4], $counts);
        $this->group('134224936', null);
        $this->version('134224936', 1);
        $this->group('106329882', '106329882');
        $this->version('106329882', 1);
        $this->group('125058276');
        $this->version('125058276', 1, 'DRAFT');
        $this->group('111563028');
        $this->version('111563028', 1);
        foreach (['212432914', '65158786'] as $zone) {
            $this->zone($zone, '98536325', $zone);
            $this->member($zone, $zone, 'CITY', '18506435', ['city_id' => '18506435']);
        }
        $batch = $resolver->destinations('245213294', ['134224936', '106329882', 'missing', '125058276', '111563028', '130636803'], CoverageAddressInput::fromArray(['city_id' => '18506435']));
        self::assertNull($batch->forGroup('134224936')->match);
        self::assertSame('CURRENT', $batch->forGroup('130636803')->match->code);
        foreach (['106329882', 'missing', '125058276', '111563028'] as $group) {
            try {
                $batch->forGroup($group);
                self::fail('Unavailable or ambiguous groups must fail when requested.');
            } catch (ApiException $error) {
                self::assertSame(422, $error->httpStatus);
                if ($group === '111563028') {
                    self::assertSame('PRICING_ZONE_AMBIGUOUS', $error->details['reason_code']);
                }
            }
        }
        self::assertNull($resolver->destinations('245213294', ['130636803'], CoverageAddressInput::fromArray([]))->forGroup('130636803')->match);
        $this->zone('45330818', '268307057', 'POLYGON');
        $this->member('45330818', '45330818', 'POLYGON', 'shape');
        foreach ([['coordinates' => [], 'field' => 'latitude'], ['coordinates' => ['latitude' => 31], 'field' => 'longitude']] as $case) {
            $batch = $resolver->destinations('245213294', ['134224936', '130636803'], CoverageAddressInput::fromArray($case['coordinates']));
            self::assertNull($batch->forGroup('130636803')->match);
            try {
                $batch->forGroup('134224936');
                self::fail('Polygon coordinates remain required.');
            } catch (ApiException $error) {
                self::assertSame('PRICING_COORDINATES_REQUIRED', $error->details['reason_code']);
                self::assertArrayHasKey('destination.'.$case['field'], $error->fieldErrors);
            }
        }
        $before = [];
        $this->measure($before, fn () => $resolver->destinations('245213294', [], CoverageAddressInput::fromArray([])));
        self::assertSame([0], $before);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.zone_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('zone_read_test');
        foreach (['pricing_zone_sets', 'pricing_zone_set_versions', 'pricing_zones', 'pricing_zone_members'] as $table) {
            (require glob(base_path('Modules/Pricing/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $clock = Mockery::mock(ClockInterface::class);
        $clock->shouldReceive('now')->andReturn(new DateTimeImmutable('2026-09-24T00:00:00Z'));
        $this->app->instance(ClockInterface::class, $clock);
    }

    private function group(string $id, ?string $tenant = '245213294'): void
    {
        RecordFixtureQuery::table('pricing_zone_sets')->insert(['pricing_zone_set_id' => $id, 'hq_id' => $tenant, 'owner_key' => $tenant ?? 'GLOBAL', 'code' => $id, 'title' => $id, 'purpose' => 'SALES', 'created_by' => '84712523']);
    }

    private function version(string $group, int $number, string $status = 'PUBLISHED', string $from = '2026-09-23 00:00:00'): void
    {
        RecordFixtureQuery::table('pricing_zone_set_versions')->insert(['zone_set_version_id' => \Tests\Support\FixtureId::from($group.'-'.$number), 'pricing_zone_set_id' => $group, 'version_number' => $number, 'status' => $status, 'valid_from' => $from, 'created_by' => '84712523']);
    }

    private function zone(string $id, string $version, string $code): void
    {
        RecordFixtureQuery::table('pricing_zones')->insert(['pricing_zone_id' => $id, 'zone_set_version_id' => $version, 'code' => $code, 'title' => $code]);
    }

    private function member(string $id, string $zone, string $type, string $reference, array $extra = []): void
    {
        RecordFixtureQuery::table('pricing_zone_members')->insert(['zone_member_id' => $id, 'pricing_zone_id' => $zone, 'member_type' => $type, 'reference_value' => $reference, 'precedence' => 1, ...$extra]);
    }

    private function measure(array &$counts, callable $read): mixed
    {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $result = $read();
            $counts[] = count(DB::connection()->getQueryLog());

            return $result;
        } finally {
            DB::connection()->disableQueryLog();
        }
    }
}
