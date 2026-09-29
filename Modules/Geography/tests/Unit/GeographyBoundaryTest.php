<?php

declare(strict_types=1);

namespace Modules\Geography\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Services\GeographyResolver;
use Modules\Geography\Application\UseCases\GetCity\GetCityCommand;
use Modules\Geography\Application\UseCases\GetCity\GetCityHandler;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;
use Modules\Geography\Infrastructure\Repositories\EloquentCityRepository;
use Tests\TestCase;

final class GeographyBoundaryTest extends TestCase
{
    public function test_active_city_in_an_inactive_province_is_not_selectable(): void
    {
        ProvinceRecord::query()->update(['is_active' => false]);
        $this->expectException(ApiException::class);
        (new GetCityHandler(new EloquentCityRepository))->handle(new GetCityCommand('18506435'));
    }

    public function test_canonical_identity_overrides_client_supplied_contact_snapshots(): void
    {
        $result = (new GeographyResolver(new EloquentCityRepository))->canonicalizeContact([
            'city_id' => '18506435',
            'city' => 'Untrusted city',
            'state' => 'Untrusted province',
            'address' => 'Retained address',
        ], true);
        self::assertSame('Canonical city', $result['city']);
        self::assertSame('Canonical province', $result['state']);
        self::assertSame('019', $result['legacy_city_code']);
        self::assertSame('Retained address', $result['address']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.geography_boundary' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('geography_boundary');
        foreach (['provinces', 'cities'] as $table) {
            (require glob(base_path('Modules/Geography/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        ProvinceRecord::query()->forceCreate(['province_id' => '125339763', 'legacy_province_code' => '01', 'name_fa' => 'Canonical province',
            'normalized_name' => '125339763', 'latitude' => 31, 'longitude' => 51, 'is_active' => true]);
        CityRecord::query()->forceCreate(['city_id' => '18506435', 'province_id' => '125339763', 'legacy_city_code' => '019',
            'name_fa' => 'Canonical city', 'normalized_name' => '18506435', 'is_active' => true]);
    }
}
