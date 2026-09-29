<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Support;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Domain\Enums\GeometryFailure;
use Modules\Geography\Domain\Enums\GeometryType;
use Modules\Geography\Domain\Policies\GeometryPolicy;
use Modules\Geography\Domain\ValueObjects\Geometry;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Geography\Domain\ValueObjects\Polygon;

/** GeoJSON is decoded once, at the application input boundary. */
final class GeoJson
{
    public static function geometry(array $input): Geometry
    {
        $geometry = self::parse($input);
        if ($geometry instanceof GeometryFailure) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, $geometry->messageKey());
        }

        return $geometry;
    }

    public static function parse(array $input): Geometry|GeometryFailure
    {
        $type = is_string($input['type'] ?? null) ? GeometryType::tryFrom($input['type']) : null;
        if ($type === null || ! is_array($input['coordinates'] ?? null)) {
            return GeometryFailure::INVALID;
        }
        $coordinates = $type === GeometryType::POLYGON ? [$input['coordinates']] : $input['coordinates'];
        if ($coordinates === []) {
            return GeometryFailure::INVALID;
        }
        $polygons = [];
        foreach ($coordinates as $polygon) {
            if (! is_array($polygon) || $polygon === []) {
                return GeometryFailure::INVALID;
            }
            $rings = [];
            foreach ($polygon as $ring) {
                $points = self::ring($ring);
                if ($points instanceof GeometryFailure) {
                    return $points;
                }
                $rings[] = $points;
            }
            $polygons[] = new Polygon($rings[0], array_slice($rings, 1));
        }

        return new Geometry($type, $polygons);
    }

    /** GeoJSON wire/storage representation; longitude precedes latitude. */
    public static function serialize(Geometry $geometry): array
    {
        $polygons = [];
        foreach ($geometry->polygons as $polygon) {
            $rings = [];
            foreach ([$polygon->outerRing, ...$polygon->holes] as $ring) {
                $rings[] = array_map(static fn (GeoPoint $point): array => [$point->longitude, $point->latitude], $ring);
            }
            $polygons[] = $rings;
        }

        return ['type' => $geometry->type->value, 'coordinates' => $geometry->type === GeometryType::POLYGON ? $polygons[0] : $polygons];
    }

    /** @return list<GeoPoint>|GeometryFailure */
    private static function ring(mixed $input): array|GeometryFailure
    {
        if (! is_array($input) || count($input) < 4) {
            return GeometryFailure::INVALID;
        }
        $points = [];
        foreach ($input as $position) {
            if (! is_array($position) || ! isset($position[0], $position[1]) || ! is_numeric($position[0]) || ! is_numeric($position[1])) {
                return GeometryFailure::INVALID;
            }
            $longitude = (float) $position[0];
            $latitude = (float) $position[1];
            if (! is_finite($longitude) || ! is_finite($latitude) || $longitude < -180 || $longitude > 180 || $latitude < -90 || $latitude > 90) {
                return GeometryFailure::INVALID;
            }
            $points[] = new GeoPoint($latitude, $longitude);
        }

        return GeometryPolicy::ringFailure($points) ?? $points;
    }
}
