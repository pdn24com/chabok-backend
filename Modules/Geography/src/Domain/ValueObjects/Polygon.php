<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\ValueObjects;

final readonly class Polygon
{
    /** @param list<GeoPoint> $outerRing @param list<list<GeoPoint>> $holes */
    public function __construct(public array $outerRing, public array $holes = []) {}
}
