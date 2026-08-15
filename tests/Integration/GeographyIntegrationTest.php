<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Modules\Geography\Domain\GeographyIds;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;

final class GeographyIntegrationTest extends MySqlRedisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(IranGeographySeeder::class)->run();
    }

    public function test_repository_seed_is_complete_deterministic_and_idempotent(): void
    {
        $this->assertDatabaseCount('provinces', 31);
        $this->assertDatabaseCount('cities', 2858);
        $this->assertSame(2858, DB::table('cities')->distinct()->count('legacy_city_code'));
        $this->assertSame(0, DB::table('cities as c')->leftJoin('provinces as p', 'p.province_id', '=', 'c.province_id')->whereNull('p.province_id')->count());

        $tehran = (array) DB::table('cities as c')->join('provinces as p', 'p.province_id', '=', 'c.province_id')
            ->where('c.legacy_city_code', '10866')->select('c.*', 'p.legacy_province_code')->first();
        $this->assertSame('تهران', $tehran['name_fa']);
        $this->assertSame('8', $tehran['legacy_province_code']);
        $this->assertSame(GeographyIds::city('10866'), $tehran['city_id']);

        $barughs = DB::table('cities')->whereIn('legacy_city_code', ['11944', '17482'])->orderBy('legacy_city_code')->get();
        $this->assertCount(2, $barughs);
        $this->assertSame(['باروق', 'باروق'], $barughs->pluck('name_fa')->all());

        $this->app->make(IranGeographySeeder::class)->run();
        $this->assertDatabaseCount('provinces', 31);
        $this->assertDatabaseCount('cities', 2858);
        $this->assertSame(GeographyIds::city('10866'), DB::table('cities')->where('legacy_city_code', '10866')->value('city_id'));
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

        DB::table('cities')->where('city_id', $tehranId)->update(['is_active' => false]);
        $this->withToken($login['token'])->getJson("/api/v1/reference/cities/{$tehranId}")->assertNotFound();
        $inactiveSearch = $this->withToken($login['token'])
            ->getJson('/api/v1/reference/cities?province_code=8&search=تهران&per_page=100')
            ->assertOk();
        $this->assertNotContains($tehranId, array_column($inactiveSearch->json('data'), 'city_id'));
    }
}
