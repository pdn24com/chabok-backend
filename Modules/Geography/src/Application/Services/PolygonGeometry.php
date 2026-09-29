<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Services;

use Modules\Geography\Application\Contracts\PolygonGeometryInterface;
use Modules\Geography\Application\Contracts\SpatialTopologyInterface;
use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\Enums\GeometryFailure;
use Modules\Geography\Domain\ValueObjects\Geometry;

/** MySQL supplies topology checks for holes and overlapping polygon components. */
final readonly class PolygonGeometry implements PolygonGeometryInterface
{
    public function __construct(private SpatialTopologyInterface $spatialTopology) {}

    public function inspect(array $geometry): Geometry|GeometryFailure
    {
        if (strlen(json_encode($geometry, JSON_THROW_ON_ERROR)) > 100000) {
            return GeometryFailure::INVALID_TOPOLOGY;
        }
        $parsed = GeoJson::parse($geometry);
        if ($parsed instanceof GeometryFailure) {
            return $parsed;
        }

        return $this->spatialTopology->isValid($parsed) ? $parsed : GeometryFailure::INVALID_TOPOLOGY;
    }

    public function intersects(Geometry $left, Geometry $right): bool
    {
        return $this->spatialTopology->intersects($left, $right);
    }

    public function contains(Geometry $geometry, float $latitude, float $longitude): bool
    {
        return $this->spatialTopology->contains($geometry, $latitude, $longitude);
    }

    /** @param array<string, Geometry> $geometries @return array<string, bool> */
    public function containsMany(array $geometries, float $latitude, float $longitude): array
    {
        return $this->spatialTopology->containsMany($geometries, $latitude, $longitude);
    }
}
