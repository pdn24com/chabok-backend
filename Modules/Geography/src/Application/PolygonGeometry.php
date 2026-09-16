<?php

declare(strict_types=1);

namespace Modules\Geography\Application;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Geography\Domain\GeoJsonGeometry;

/** Shared coordinate normalization with MySQL topology checks, including holes. */
final class PolygonGeometry
{
    public function normalize(array $geometry): array
    {
        if (strlen(json_encode($geometry, JSON_THROW_ON_ERROR)) > 100000) $this->invalid();
        $geometry = GeoJsonGeometry::normalize($geometry);
        try {
            $valid = DB::selectOne('SELECT ST_IsValid(ST_GeomFromGeoJSON(?, 1, 0)) AS valid', [json_encode($geometry, JSON_THROW_ON_ERROR)]);
        } catch (QueryException) {
            $this->invalid();
        }
        if (! $valid->valid) $this->invalid();
        return $geometry;
    }

    public function intersects(array $left, array $right): bool
    {
        return (bool) DB::selectOne('SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), ST_GeomFromGeoJSON(?, 1, 0)) AS matched', [json_encode($left, JSON_THROW_ON_ERROR), json_encode($right, JSON_THROW_ON_ERROR)])->matched;
    }

    public function contains(array $geometry, float $latitude, float $longitude): bool
    {
        return (bool) DB::selectOne('SELECT ST_Intersects(ST_GeomFromGeoJSON(?, 1, 0), POINT(?, ?)) AS matched', [json_encode($geometry, JSON_THROW_ON_ERROR), $longitude, $latitude])->matched;
    }

    private function invalid(): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, 'محدودهٔ چندضلعی نامعتبر است؛ خطوط، حفره‌ها و مختصات را اصلاح کنید.', details: ['reason_code' => 'PRICING_POLYGON_INVALID']);
    }
}
