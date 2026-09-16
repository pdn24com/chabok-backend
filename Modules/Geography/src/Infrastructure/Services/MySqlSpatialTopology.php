<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Geography\Application\Contracts\SpatialTopology;

final class MySqlSpatialTopology implements SpatialTopology
{
    public function isValid(array $geometry): bool
    {
        try {
            return (bool) DB::selectOne('SELECT ST_IsValid(ST_GeomFromGeoJSON(?, 1, 0)) AS valid', [json_encode($geometry, JSON_THROW_ON_ERROR)])->valid;
        } catch (QueryException) {
            return false;
        }
    }

    public function intersects(array $left, array $right): bool
    {
        return (bool) DB::selectOne('SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), ST_GeomFromGeoJSON(?, 1, 0)) AS matched', [json_encode($left, JSON_THROW_ON_ERROR), json_encode($right, JSON_THROW_ON_ERROR)])->matched;
    }

    public function contains(array $geometry, float $latitude, float $longitude): bool
    {
        return (bool) DB::selectOne('SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), POINT(?, ?)) AS matched', [json_encode($geometry, JSON_THROW_ON_ERROR), $longitude, $latitude])->matched;
    }
}
