<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Geography\Infrastructure\Database\Seeders\CountrySeeder;
use Modules\Geography\Infrastructure\Persistence\Models\CountryRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InstallsNumericSchema;
use Tests\TestCase;

final class CountryReferenceTest extends TestCase
{
    use InstallsNumericSchema;

    public function test_seed_is_complete_and_repeatable_without_changing_ids_or_reactivating_countries(): void
    {
        $this->assertDatabaseCount('countries', 249);
        foreach (['country_code', 'alpha3_code', 'numeric_code'] as $column) {
            self::assertSame(249, CountryRecord::query()->distinct()->count($column));
        }
        $this->assertDatabaseHas('countries', ['country_code' => 'IR', 'alpha3_code' => 'IRN', 'numeric_code' => '364', 'name_fa' => 'ایران', 'name_en' => 'Iran']);
        $this->assertDatabaseHas('countries', ['country_code' => 'AF', 'numeric_code' => '004']);
        $ids = CountryRecord::query()->orderBy('country_code')->pluck('id', 'country_code')->all();
        $iran = CountryRecord::query()->where('country_code', 'IR')->sole();
        $createdAt = $iran->created_at;
        $iran->forceFill(['is_active' => false, 'name_fa' => 'Outdated name'])->save();

        $this->travel(1)->days();
        $this->seed(CountrySeeder::class);

        $this->assertDatabaseCount('countries', 249);
        self::assertSame($ids, CountryRecord::query()->orderBy('country_code')->pluck('id', 'country_code')->all());
        $iran->refresh();
        self::assertSame($createdAt, $iran->created_at);
        self::assertFalse($iran->is_active);
        self::assertSame('ایران', $iran->name_fa);
        self::assertSame((string) $iran->id, $iran->country_id);
    }

    public function test_default_database_seed_populates_countries_automatically(): void
    {
        $this->installNumericSchema();
        $this->assertDatabaseCount('countries', 0);

        $this->seed();

        $this->assertDatabaseCount('countries', 249);
        $this->assertDatabaseCount('provinces', 31);
        $this->assertDatabaseCount('cities', 2858);
        $this->assertDatabaseCount('pricing_charge_types', 12);
        $countryIds = CountryRecord::query()->orderBy('country_code')->pluck('id', 'country_code')->all();
        $chargeTypeIds = DB::table('pricing_charge_types')->orderBy('code')->pluck('id', 'code')->all();

        $this->seed();

        self::assertSame($countryIds, CountryRecord::query()->orderBy('country_code')->pluck('id', 'country_code')->all());
        self::assertSame($chargeTypeIds, DB::table('pricing_charge_types')->orderBy('code')->pluck('id', 'code')->all());
    }

    public function test_country_routes_require_authentication_and_completed_password_change(): void
    {
        $countryId = CountryRecord::query()->where('country_code', 'IR')->sole()->country_id;
        $this->getJson('/api/v1/reference/countries')->assertUnauthorized();
        $this->getJson('/api/v1/reference/countries/'.$countryId)->assertUnauthorized();

        $this->authenticateReader(mustChangePassword: true);
        $this->getJson('/api/v1/reference/countries')->assertForbidden();
        $this->getJson('/api/v1/reference/countries/'.$countryId)->assertForbidden();
    }

    public function test_list_returns_all_countries_by_default_and_supports_stable_pagination(): void
    {
        $this->authenticateReader();
        $this->getJson('/api/v1/reference/countries')
            ->assertOk()->assertJsonCount(249, 'data')
            ->assertJsonPath('meta.pagination.total', 249)
            ->assertJsonPath('meta.pagination.page_size', 250)
            ->assertJsonPath('meta.pagination.total_pages', 1)
            ->assertJsonPath('data.0.country_code', 'AF')
            ->assertJsonPath('data.0.numeric_code', '004')
            ->assertJsonMissingPath('data.0.normalized_name');

        $first = $this->getJson('/api/v1/reference/countries?per_page=10')
            ->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.pagination.total_pages', 25);
        $second = $this->getJson('/api/v1/reference/countries?per_page=10&page=2')
            ->assertOk()->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.pagination.page', 2);
        self::assertSame([], array_intersect(array_column($first->json('data'), 'country_id'), array_column($second->json('data'), 'country_id')));
    }

    #[DataProvider('searchTerms')]
    public function test_search_supports_normalized_persian_english_and_country_codes(string $term, string $code): void
    {
        $this->authenticateReader();
        $response = $this->getJson('/api/v1/reference/countries?'.http_build_query(['search' => $term]))->assertOk();
        self::assertContains($code, array_column($response->json('data'), 'country_code'));
    }

    public static function searchTerms(): array
    {
        return [
            'Arabic yeh' => ['ايران', 'IR'],
            'Arabic kaf and yeh' => ['كويت', 'KW'],
            'English case' => ['uNiTeD sTaTeS', 'US'],
            'Alpha-2 code' => ['ir', 'IR'],
            'Alpha-3 code' => ['irn', 'IR'],
            'Numeric code with leading zeros' => ['004', 'AF'],
        ];
    }

    public function test_detail_returns_the_public_country_fields(): void
    {
        $this->authenticateReader();
        $iran = CountryRecord::query()->where('country_code', 'IR')->sole();

        $this->getJson('/api/v1/reference/countries/'.$iran->country_id)->assertOk()->assertJsonPath('data', [
            'country_id' => $iran->country_id,
            'country_code' => 'IR',
            'alpha3_code' => 'IRN',
            'numeric_code' => '364',
            'name_fa' => 'ایران',
            'name_en' => 'Iran',
            'is_active' => true,
        ]);
    }

    public function test_inactive_and_missing_countries_are_hidden_and_search_respects_the_active_filter(): void
    {
        $this->authenticateReader();
        $iran = CountryRecord::query()->where('country_code', 'IR')->sole();
        $iran->forceFill(['is_active' => false])->save();

        $this->getJson('/api/v1/reference/countries?search=Iran')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/reference/countries?search=IRN&active=0')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_active', false);
        $this->getJson('/api/v1/reference/countries?active=0&search=United')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/reference/countries/'.$iran->country_id)->assertNotFound();
        $this->getJson('/api/v1/reference/countries/999999')->assertNotFound();
        $this->getJson('/api/v1/reference/countries/not-an-id')->assertNotFound();
        $this->getJson('/api/v1/reference/countries?search=nonexistent-country')->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters): void
    {
        $this->authenticateReader();
        $this->getJson('/api/v1/reference/countries?'.http_build_query($filters))->assertUnprocessable();
    }

    public static function invalidFilters(): array
    {
        return [
            'Zero page' => [['page' => 0]],
            'Zero page size' => [['per_page' => 0]],
            'Oversized page' => [['per_page' => 251]],
            'Non-boolean active' => [['active' => 'invalid']],
            'Long search' => [['search' => str_repeat('a', 161)]],
            'Non-string search' => [['search' => ['IR']]],
        ];
    }

    #[DataProvider('uniqueCodes')]
    public function test_country_codes_are_unique_in_the_database(string $column): void
    {
        $iran = CountryRecord::query()->where('country_code', 'IR')->sole();
        $us = CountryRecord::query()->where('country_code', 'US')->sole();

        $this->expectException(QueryException::class);
        $us->forceFill([$column => $iran->getAttribute($column)])->save();
    }

    public static function uniqueCodes(): array
    {
        return [['country_code'], ['alpha3_code'], ['numeric_code']];
    }

    public function test_countries_migration_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require base_path('Modules/Geography/database/migrations/2026_01_01_000110_create_countries.php');
        $migration->down();
        self::assertFalse(Schema::hasTable('countries'));

        $migration->up();
        $this->seed(CountrySeeder::class);
        $this->assertDatabaseCount('countries', 249);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require base_path('Modules/Geography/database/migrations/2026_01_01_000110_create_countries.php'))->up();
        $this->seed(CountrySeeder::class);
    }

    private function authenticateReader(bool $mustChangePassword = false): void
    {
        $claims = new AccessTokenClaims('1', 'country-reader-session', null, $mustChangePassword, 'country-reader-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('country-reader')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $this->withToken('country-reader');
    }
}
