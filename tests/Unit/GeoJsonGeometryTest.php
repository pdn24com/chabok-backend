<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Foundation\Domain\ApiException;
use Modules\Operations\Domain\GeoJsonGeometry;
use PHPUnit\Framework\TestCase;

final class GeoJsonGeometryTest extends TestCase
{
    public function test_polygon_boundary_and_holes_are_deterministic(): void
    {
        $polygon = ['type' => 'Polygon', 'coordinates' => [[
            [48.0, 30.0], [49.0, 30.0], [49.0, 31.0], [48.0, 31.0], [48.0, 30.0],
        ], [
            [48.4, 30.4], [48.6, 30.4], [48.6, 30.6], [48.4, 30.6], [48.4, 30.4],
        ]]];

        self::assertTrue(GeoJsonGeometry::contains($polygon, 30.2, 48.2));
        self::assertTrue(GeoJsonGeometry::contains($polygon, 30.0, 48.5), 'Outer boundary is included.');
        self::assertFalse(GeoJsonGeometry::contains($polygon, 30.5, 48.5), 'A point inside a hole is excluded.');
        self::assertFalse(GeoJsonGeometry::contains($polygon, 32.0, 48.5));
    }

    public function test_multi_polygon_and_radius_include_exact_boundaries(): void
    {
        $multi = ['type' => 'MultiPolygon', 'coordinates' => [[[[46.0, 38.0], [47.0, 38.0], [47.0, 39.0], [46.0, 39.0], [46.0, 38.0]]], [[[50.0, 35.0], [51.0, 35.0], [51.0, 36.0], [50.0, 36.0], [50.0, 35.0]]]]];
        self::assertTrue(GeoJsonGeometry::contains($multi, 38.5, 46.5));
        self::assertTrue(GeoJsonGeometry::withinRadius(35.6892, 51.3890, 35.6892, 51.3890, 1));
        self::assertFalse(GeoJsonGeometry::withinRadius(38.0800, 46.2919, 35.6892, 51.3890, 1000));
    }

    public function test_self_intersecting_and_unclosed_rings_fail_validation(): void
    {
        foreach ([
            ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 1], [1, 0], [0, 1], [0, 0]]]],
            ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 1]]]],
        ] as $geometry) {
            try { GeoJsonGeometry::normalize($geometry); self::fail('Invalid geometry must fail.'); }
            catch (ApiException $exception) { self::assertSame(422, $exception->httpStatus); }
        }
    }
}
