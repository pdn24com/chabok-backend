<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Contracts;

interface SpatialTopology
{
    public function isValid(array $geometry): bool;

    public function intersects(array $left, array $right): bool;

    public function contains(array $geometry, float $latitude, float $longitude): bool;
}
