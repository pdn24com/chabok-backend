<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Modules\Geography\Application\Contracts\SpatialTopologyInterface;
use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;
use Tests\Support\GeographyIds;
use Tests\Support\RecordFixtureQuery;

final class GeographyIntegrationTest extends MySqlRedisTestCase
{
    public function test_repository_seed_is_complete_deterministic_and_idempotent(): void
    {
        $this->assertDatabaseCount('provinces', 31);
        $this->assertDatabaseCount('cities', 2858);
        $this->assertSame(2858, RecordFixtureQuery::table('cities')->distinct()->count('legacy_city_code'));
        $this->assertSame(0, RecordFixtureQuery::table('cities as c')->leftJoin('provinces as p', 'p.id', '=', 'c.province_id')->whereNull('p.province_id')->count());

        $tehran = (array) RecordFixtureQuery::table('cities as c')->join('provinces as p', 'p.id', '=', 'c.province_id')
            ->where('c.legacy_city_code', '10866')->select('c.*', 'p.legacy_province_code')->first();
        $this->assertSame('تهران', $tehran['name_fa']);
        $this->assertSame('8', $tehran['legacy_province_code']);
        $this->assertSame(GeographyIds::city('10866'), $tehran['city_id']);

        $barughs = RecordFixtureQuery::table('cities')->whereIn('legacy_city_code', ['11944', '17482'])->orderBy('legacy_city_code')->get();
        $this->assertCount(2, $barughs);
        $this->assertSame(['باروق', 'باروق'], $barughs->pluck('name_fa')->all());

        $this->app->make(IranGeographySeeder::class)->run();
        $this->assertDatabaseCount('provinces', 31);
        $this->assertDatabaseCount('cities', 2858);
        $this->assertSame(GeographyIds::city('10866'), RecordFixtureQuery::table('cities')->where('legacy_city_code', '10866')->value('city_id'));
    }

    public function test_authenticated_reference_routes_paginate_filter_normalize_and_hide_inactive_cities(): void
    {
        $tenant = $this->tenant('GEO-A');
        $this->user($tenant['hq_id'], 'geography-reader');
        $login = $this->login('geography-reader');

        $this->getJson('/api/v1/reference/provinces')->assertUnauthorized();

        $this->withToken($login['token'])->getJson('/api/v1/reference/provinces?per_page=10')
            ->assertOk()->assertJsonPath('meta.pagination.total', 31)
            ->assertJsonCount(10, 'data');

        $response = $this->withToken($login['token'])
            ->getJson('/api/v1/reference/cities?province_code=20&search=كرمان&per_page=10')
            ->assertOk()->assertJsonPath('meta.pagination.page_size', 10);
        $this->assertGreaterThan(0, $response->json('meta.pagination.total'));
        $this->assertSame(['20'], array_values(array_unique(array_map(
            fn (array $city): string => (string) $city['province']['legacy_province_code'],
            $response->json('data'),
        ))));

        $tehranId = GeographyIds::city('10866');
        $this->withToken($login['token'])->getJson("/api/v1/reference/cities/{$tehranId}")
            ->assertOk()->assertJsonPath('data.legacy_city_code', '10866')
            ->assertJsonPath('data.province.legacy_province_code', '8');

        RecordFixtureQuery::table('cities')->where('city_id', $tehranId)->update(['is_active' => false]);
        $this->withToken($login['token'])->getJson("/api/v1/reference/cities/{$tehranId}")->assertNotFound();
        $inactiveSearch = $this->withToken($login['token'])
            ->getJson('/api/v1/reference/cities?province_code=8&search=تهران&per_page=100')
            ->assertOk();
        $this->assertNotContains($tehranId, array_column($inactiveSearch->json('data'), 'city_id'));
    }

    public function test_spatial_containment_batches_queries_and_preserves_boundaries_and_holes(): void
    {
        $topology = $this->app->make(SpatialTopologyInterface::class);
        $solid = GeoJson::geometry(['type' => 'Polygon', 'coordinates' => [[[50, 30], [54, 30], [54, 34], [50, 34], [50, 30]]]]);
        $withHole = GeoJson::geometry(['type' => 'Polygon', 'coordinates' => [
            [[50, 30], [54, 30], [54, 34], [50, 34], [50, 30]], [[51, 31], [51, 33], [53, 33], [53, 31], [51, 31]],
        ]]);
        $counts = [];
        foreach ([1, 40, 101] as $size) {
            $geometries = [];
            for ($index = 0; $index < $size; $index++) {
                $geometries['polygon-'.$index] = $solid;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $matches = $topology->containsMany($geometries, 32, 52);
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($size, $matches);
            self::assertSame(array_fill_keys(array_keys($geometries), true), $matches);
        }
        self::assertSame([1, 1, 2], $counts);
        self::assertSame(['solid' => true, 'hole' => false], $topology->containsMany(['solid' => $solid, 'hole' => $withHole], 32, 52));
        self::assertSame(['solid' => true], $topology->containsMany(['solid' => $solid], 30, 50));
        self::assertSame([], $topology->containsMany([], 32, 52));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(IranGeographySeeder::class)->run();
    }
}
