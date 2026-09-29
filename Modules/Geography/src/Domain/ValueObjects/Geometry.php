<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\ValueObjects;

use Modules\Geography\Domain\Enums\GeometryType;

final readonly class Geometry
{
    /** @param list<Polygon> $polygons */
    public function __construct(public GeometryType $type, public array $polygons) {}
}
