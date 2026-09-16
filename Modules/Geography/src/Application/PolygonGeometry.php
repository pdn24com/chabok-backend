<?php

declare(strict_types=1);

namespace Modules\Geography\Application;

use Modules\Geography\Application\Contracts\SpatialTopology;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Geography\Domain\GeoJsonGeometry;
/** Shared coordinate normalization with MySQL topology checks, including holes. */

final readonly class PolygonGeometry
{
    public function __construct(private SpatialTopology $topology)
    {
    }

    public function normalize(array $geometry): array
    {
        if (strlen(json_encode($geometry, JSON_THROW_ON_ERROR)) > 100000) {
            $this->invalid();
        }
        $geometry = GeoJsonGeometry::normalize($geometry);
        if (!$this->topology->isValid($geometry)) {
            $this->invalid();
        }
        return $geometry;
    }

    public function intersects(array $left, array $right): bool
    {
        return $this->topology->intersects($left, $right);
    }

    public function contains(array $geometry, float $latitude, float $longitude): bool
    {
        return $this->topology->contains($geometry, $latitude, $longitude);
    }

    private function invalid(): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, 'محدودهٔ چندضلعی نامعتبر است؛ خطوط، حفره‌ها و مختصات را اصلاح کنید.', details: ['reason_code' => 'PRICING_POLYGON_INVALID']);
    }
}
