<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Adapters;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Geography\Application\Contracts\SpatialTopologyInterface;
use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\ValueObjects\Geometry;

final class MySqlSpatialTopology implements SpatialTopologyInterface
{
    public function isValid(Geometry $geometry): bool
    {
        try {
            return (bool) DB::selectOne('SELECT ST_IsValid(ST_GeomFromGeoJSON(?, 1, 0)) AS valid', [json_encode(GeoJson::serialize($geometry), JSON_THROW_ON_ERROR)])->valid;
        } catch (QueryException) {
            return false;
        }
    }

    public function intersects(Geometry $left, Geometry $right): bool
    {
        return (bool) DB::selectOne('SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), ST_GeomFromGeoJSON(?, 1, 0)) AS matched', [json_encode(GeoJson::serialize($left), JSON_THROW_ON_ERROR), json_encode(GeoJson::serialize($right), JSON_THROW_ON_ERROR)])->matched;
    }

    public function contains(
        Geometry $geometry,
        float $latitude,
        float $longitude,
    ): bool {
        return (bool) DB::selectOne('SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), POINT(?, ?)) AS matched', [json_encode(GeoJson::serialize($geometry), JSON_THROW_ON_ERROR), $longitude, $latitude])->matched;
    }

    /** @param array<string, Geometry> $geometries @return array<string, bool> */
    public function containsMany(array $geometries, float $latitude, float $longitude): array
    {
        $matches = [];
        foreach (array_chunk($geometries, 100, true) as $batch) {
            $expressions = [];
            $bindings = [];
            foreach (array_values($batch) as $index => $geometry) {
                // Aliases are generated integers; all geometry and coordinate values are bound.
                $expressions[] = 'ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), POINT(?, ?)) AS matched_'.$index;
                array_push($bindings, json_encode(GeoJson::serialize($geometry), JSON_THROW_ON_ERROR), $longitude, $latitude);
            }
            $result = DB::selectOne('SELECT '.implode(', ', $expressions), $bindings);
            foreach (array_keys($batch) as $index => $key) {
                $matches[$key] = (bool) $result->{'matched_'.$index};
            }
        }

        return $matches;
    }
}
