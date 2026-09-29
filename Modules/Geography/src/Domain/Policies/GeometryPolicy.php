<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\Policies;

use Modules\Geography\Domain\Enums\GeometryFailure;
use Modules\Geography\Domain\ValueObjects\Geometry;
use Modules\Geography\Domain\ValueObjects\GeoPoint;

final class GeometryPolicy
{
    public static function contains(Geometry $geometry, GeoPoint $point): bool
    {
        foreach ($geometry->polygons as $polygon) {
            if (! self::pointInRing($polygon->outerRing, $point)) {
                continue;
            }
            $insideHole = false;
            foreach ($polygon->holes as $hole) {
                if (self::pointInRing($hole, $point)) {
                    $insideHole = true;
                    break;
                }
            }
            if (! $insideHole) {
                return true;
            }
        }

        return false;
    }

    /** @param list<GeoPoint> $ring */
    public static function ringFailure(array $ring): ?GeometryFailure
    {
        if (count($ring) < 4) {
            return GeometryFailure::INVALID;
        }
        if ($ring[0] != $ring[count($ring) - 1]) {
            return GeometryFailure::RING_NOT_CLOSED;
        }
        $segments = count($ring) - 1;
        for ($i = 0; $i < $segments; $i++) {
            for ($j = $i + 1; $j < $segments; $j++) {
                if (abs($i - $j) <= 1 || $i === 0 && $j === $segments - 1) {
                    continue;
                }
                if (self::segmentsIntersect($ring[$i], $ring[$i + 1], $ring[$j], $ring[$j + 1])) {
                    return GeometryFailure::SELF_INTERSECTION;
                }
            }
        }

        return null;
    }

    public static function withinRadius(
        GeoPoint $point,
        GeoPoint $center,
        int $radiusMeters,
    ): bool {
        $earth = 6371008.8;
        $lat1 = deg2rad($center->latitude);
        $lat2 = deg2rad($point->latitude);
        $dLat = $lat2 - $lat1;
        $dLon = deg2rad($point->longitude - $center->longitude);
        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a)) <= $radiusMeters;
    }

    /** @param list<GeoPoint> $ring */
    private static function pointInRing(
        array $ring,
        GeoPoint $point,
    ): bool {
        $x = $point->longitude;
        $y = $point->latitude;
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = [$ring[$i]->longitude, $ring[$i]->latitude];
            [$xj, $yj] = [$ring[$j]->longitude, $ring[$j]->latitude];
            if (self::onSegment($ring[$j], $ring[$i], $point)) {
                return true;
            }
            if ($yi > $y !== $yj > $y && $x <= ($xj - $xi) * ($y - $yi) / ($yj - $yi ?: PHP_FLOAT_EPSILON) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private static function segmentsIntersect(
        GeoPoint $a,
        GeoPoint $b,
        GeoPoint $c,
        GeoPoint $d,
    ): bool {
        $o1 = self::orientation($a, $b, $c);
        $o2 = self::orientation($a, $b, $d);
        $o3 = self::orientation($c, $d, $a);
        $o4 = self::orientation($c, $d, $b);

        return $o1 !== $o2 && $o3 !== $o4 || $o1 === 0 && self::onSegment($a, $b, $c) || $o2 === 0 && self::onSegment($a, $b, $d) || $o3 === 0 && self::onSegment($c, $d, $a) || $o4 === 0 && self::onSegment($c, $d, $b);
    }

    private static function orientation(
        GeoPoint $a,
        GeoPoint $b,
        GeoPoint $c,
    ): int {
        $v = ($b->latitude - $a->latitude) * ($c->longitude - $b->longitude) - ($b->longitude - $a->longitude) * ($c->latitude - $b->latitude);

        return abs($v) < 1.0E-10 ? 0 : ($v > 0 ? 1 : 2);
    }

    private static function onSegment(
        GeoPoint $a,
        GeoPoint $b,
        GeoPoint $p,
    ): bool {
        $cross = ($p->latitude - $a->latitude) * ($b->longitude - $a->longitude) - ($p->longitude - $a->longitude) * ($b->latitude - $a->latitude);

        return abs($cross) < 1.0E-9 && $p->longitude >= min($a->longitude, $b->longitude) - 1.0E-9 && $p->longitude <= max($a->longitude, $b->longitude) + 1.0E-9 && $p->latitude >= min($a->latitude, $b->latitude) - 1.0E-9 && $p->latitude <= max($a->latitude, $b->latitude) + 1.0E-9;
    }
}
