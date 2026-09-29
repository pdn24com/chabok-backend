<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\Enums\GeometryFailure;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Operations\Domain\Enums\CoverageCriterionType as Type;
use Modules\Operations\Domain\Policies\CoverageMatchPolicy;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;
use PHPUnit\Framework\TestCase;

final class CoverageMatchPolicyTest extends TestCase
{
    public function test_priority_precedes_specificity_and_equal_specificity_is_a_tie(): void
    {
        $policy = new CoverageMatchPolicy;
        self::assertGreaterThan(0, $policy->compare(new CoverageCriterion(Type::PROVINCE, 1), new CoverageCriterion(Type::POLYGON, 0)));
        self::assertGreaterThan(0, $policy->compare(new CoverageCriterion(Type::CITY, -100000), new CoverageCriterion(Type::PROVINCE, -100000)));
        self::assertSame(0, $policy->compare(new CoverageCriterion(Type::POLYGON, 100000), new CoverageCriterion(Type::POINT_RADIUS, 100000)));
    }

    public function test_postal_bounds_are_inclusive_and_keep_leading_zeroes(): void
    {
        $policy = new CoverageMatchPolicy;
        $rule = new CoverageCriterion(Type::POSTAL_RANGE, 1, postalFrom: '0010000000', postalTo: '0019999999');
        foreach (['0010000000', '0015000000', '0019999999'] as $code) {
            self::assertTrue($policy->matches($rule, new CoverageLocation(postalCode: $code)));
        }
        foreach (['0009999999', '0020000000', null] as $code) {
            self::assertFalse($policy->matches($rule, new CoverageLocation(postalCode: $code)));
        }
    }

    public function test_missing_location_cannot_match_spatial_rules(): void
    {
        $policy = new CoverageMatchPolicy;
        $radius = new CoverageCriterion(Type::POINT_RADIUS, 1, center: new GeoPoint(30, 48), radiusMeters: 100);
        self::assertFalse($policy->matches($radius, new CoverageLocation));
        self::assertTrue($policy->matches($radius, new CoverageLocation(point: new GeoPoint(30, 48))));
        self::assertFalse($policy->matches($radius, new CoverageLocation(point: new GeoPoint(31, 48))));
        self::assertFalse($policy->matches(new CoverageCriterion(Type::CITY, 1, cityId: '18506435'), new CoverageLocation));
    }

    public function test_geojson_boundary_rejects_malformed_positions_without_throwing(): void
    {
        foreach ([[], ['type' => 'Point', 'coordinates' => [1, 2]],
            ['type' => 'Polygon', 'coordinates' => [[['x' => 1, 'y' => 2], [1, 0], [1, 1], [0, 0]]]],
            ['type' => 'Polygon', 'coordinates' => [[[INF, 0], [1, 0], [1, 1], [INF, 0]]]],
        ] as $input) {
            self::assertSame(GeometryFailure::INVALID, GeoJson::parse($input));
        }
        $input = ['type' => 'Polygon', 'coordinates' => [[[0, 0, 9], [1, 0], [1, 1], [0, 0]]]];
        $output = GeoJson::serialize(GeoJson::geometry($input));
        self::assertSame([0.0, 0.0], $output['coordinates'][0][0]);
    }
}
