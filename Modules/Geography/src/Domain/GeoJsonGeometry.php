<?php

declare(strict_types=1);

namespace Modules\Geography\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class GeoJsonGeometry
{
    /** @param array<string,mixed> $geometry @return array<string,mixed> */
    public static function normalize(array $geometry): array
    {
        $type = $geometry['type'] ?? null;
        if (! in_array($type, ['Polygon', 'MultiPolygon'], true) || ! is_array($geometry['coordinates'] ?? null)) self::invalid();
        $polygons = $type === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        if ($polygons === []) self::invalid();
        $normalized = [];
        foreach ($polygons as $polygon) {
            if (! is_array($polygon) || $polygon === []) self::invalid();
            $rings = [];
            foreach ($polygon as $ring) $rings[] = self::normalizeRing($ring);
            $normalized[] = $rings;
        }
        return ['type' => $type, 'coordinates' => $type === 'Polygon' ? $normalized[0] : $normalized];
    }

    /** @param array<string,mixed> $geometry */
    public static function contains(array $geometry, float $latitude, float $longitude): bool
    {
        $geometry = self::normalize($geometry);
        $polygons = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];
        foreach ($polygons as $polygon) {
            if (! self::pointInRing($polygon[0], $longitude, $latitude)) continue;
            $insideHole = false;
            foreach (array_slice($polygon, 1) as $hole) if (self::pointInRing($hole, $longitude, $latitude)) { $insideHole = true; break; }
            if (! $insideHole) return true;
        }
        return false;
    }

    public static function withinRadius(float $latitude, float $longitude, float $centerLatitude, float $centerLongitude, int $radiusMeters): bool
    {
        $earth = 6371008.8; $lat1 = deg2rad($centerLatitude); $lat2 = deg2rad($latitude);
        $dLat = $lat2 - $lat1; $dLon = deg2rad($longitude - $centerLongitude);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a)) <= $radiusMeters;
    }

    /** @param mixed $ring @return list<array{0:float,1:float}> */
    private static function normalizeRing(mixed $ring): array
    {
        if (! is_array($ring) || count($ring) < 4) self::invalid();
        $result = [];
        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2 || ! is_numeric($point[0]) || ! is_numeric($point[1])) self::invalid();
            $lng = (float) $point[0]; $lat = (float) $point[1];
            if ($lng < -180 || $lng > 180 || $lat < -90 || $lat > 90) self::invalid();
            $result[] = [$lng, $lat];
        }
        if ($result[0] !== $result[count($result) - 1]) self::invalid('Polygon rings must be closed.');
        $segments = count($result) - 1;
        for ($i = 0; $i < $segments; $i++) for ($j = $i + 1; $j < $segments; $j++) {
            if (abs($i - $j) <= 1 || ($i === 0 && $j === $segments - 1)) continue;
            if (self::segmentsIntersect($result[$i], $result[$i + 1], $result[$j], $result[$j + 1])) self::invalid('Polygon rings cannot self-intersect.');
        }
        return $result;
    }

    /** @param list<array{0:float,1:float}> $ring */
    private static function pointInRing(array $ring, float $x, float $y): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = $ring[$i]; [$xj, $yj] = $ring[$j];
            if (self::onSegment([$xj, $yj], [$xi, $yi], [$x, $y])) return true;
            if (($yi > $y) !== ($yj > $y) && $x <= ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: PHP_FLOAT_EPSILON) + $xi) $inside = ! $inside;
        }
        return $inside;
    }

    private static function segmentsIntersect(array $a, array $b, array $c, array $d): bool
    {
        $o1 = self::orientation($a, $b, $c); $o2 = self::orientation($a, $b, $d); $o3 = self::orientation($c, $d, $a); $o4 = self::orientation($c, $d, $b);
        return ($o1 !== $o2 && $o3 !== $o4) || ($o1 === 0 && self::onSegment($a, $b, $c)) || ($o2 === 0 && self::onSegment($a, $b, $d)) || ($o3 === 0 && self::onSegment($c, $d, $a)) || ($o4 === 0 && self::onSegment($c, $d, $b));
    }

    private static function orientation(array $a, array $b, array $c): int
    {
        $v = ($b[1] - $a[1]) * ($c[0] - $b[0]) - ($b[0] - $a[0]) * ($c[1] - $b[1]);
        return abs($v) < 1e-10 ? 0 : ($v > 0 ? 1 : 2);
    }

    private static function onSegment(array $a, array $b, array $p): bool
    {
        $cross = ($p[1] - $a[1]) * ($b[0] - $a[0]) - ($p[0] - $a[0]) * ($b[1] - $a[1]);
        return abs($cross) < 1e-9 && $p[0] >= min($a[0], $b[0]) - 1e-9 && $p[0] <= max($a[0], $b[0]) + 1e-9 && $p[1] >= min($a[1], $b[1]) - 1e-9 && $p[1] <= max($a[1], $b[1]) + 1e-9;
    }

    private static function invalid(string $message = 'The GeoJSON geometry is invalid.'): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, $message);
    }
}
