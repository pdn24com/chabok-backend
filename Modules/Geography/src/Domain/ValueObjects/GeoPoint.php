<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\ValueObjects;

final readonly class GeoPoint
{
    public function __construct(public float $latitude, public float $longitude) {}
}
