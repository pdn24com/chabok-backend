<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Contracts;

use Modules\Geography\Domain\ValueObjects\Geometry;

interface SpatialTopologyInterface
{
    public function isValid(Geometry $geometry): bool;

    public function intersects(Geometry $left, Geometry $right): bool;

    public function contains(
        Geometry $geometry,
        float $latitude,
        float $longitude,
    ): bool;

    /** @param array<string, Geometry> $geometries @return array<string, bool> */
    public function containsMany(array $geometries, float $latitude, float $longitude): array;
}
