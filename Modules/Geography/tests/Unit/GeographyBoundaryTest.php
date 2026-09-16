<?php

declare(strict_types=1);

namespace Modules\Geography\Tests\Unit;

use Modules\Foundation\Domain\ApiException;
use Modules\Geography\Application\GeographyResolver;
use Modules\Geography\Application\Repositories\GeographyRepository;
use Modules\Geography\Application\UseCases\GetCity\GetCityCommand;
use Modules\Geography\Application\UseCases\GetCity\GetCityHandler;
use PHPUnit\Framework\TestCase;

final class GeographyBoundaryTest extends TestCase
{
    public function test_active_city_in_an_inactive_province_is_not_selectable(): void
    {
        $repository = $this->createMock(GeographyRepository::class);
        $repository->expects(self::once())->method('city')->with('city')->willReturn((object) ['is_active' => 1, 'province_active' => 0]);
        $this->expectException(ApiException::class);
        (new GetCityHandler($repository))->handle(new GetCityCommand('city'));
    }

    public function test_canonical_identity_overrides_client_supplied_contact_snapshots(): void
    {
        $repository = $this->createMock(GeographyRepository::class);
        $repository->expects(self::once())->method('city')->with('city')->willReturn((object) [
            'city_id' => 'city',
            'is_active' => 1,
            'province_active' => 1,
            'name_fa' => 'Canonical city',
            'province_name_fa' => 'Canonical province',
            'province_id' => 'province',
            'legacy_city_code' => '019',
        ]);
        $result = (new GeographyResolver($repository))->canonicalizeContact([
            'city_id' => 'city',
            'city' => 'Untrusted city',
            'state' => 'Untrusted province',
            'address' => 'Retained address',
        ], true);
        self::assertSame('Canonical city', $result['city']);
        self::assertSame('Canonical province', $result['state']);
        self::assertSame('019', $result['legacy_city_code']);
        self::assertSame('Retained address', $result['address']);
    }
}
