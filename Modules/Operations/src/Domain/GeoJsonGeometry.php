<?php

declare(strict_types=1);

namespace Modules\Operations\Domain;

/** Compatibility facade; shared geometry now belongs to Geography. */
final class GeoJsonGeometry
{
    public static function normalize(array $geometry): array { return \Modules\Geography\Domain\GeoJsonGeometry::normalize($geometry); }
    public static function contains(array $geometry, float $latitude, float $longitude): bool { return \Modules\Geography\Domain\GeoJsonGeometry::contains($geometry, $latitude, $longitude); }
    public static function withinRadius(float $latitude, float $longitude, float $centerLatitude, float $centerLongitude, int $radiusMeters): bool { return \Modules\Geography\Domain\GeoJsonGeometry::withinRadius($latitude, $longitude, $centerLatitude, $centerLongitude, $radiusMeters); }
}