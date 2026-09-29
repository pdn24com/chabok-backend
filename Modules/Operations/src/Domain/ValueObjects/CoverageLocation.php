<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\ValueObjects;

use Modules\Geography\Domain\ValueObjects\GeoPoint;

final readonly class CoverageLocation
{
    public function __construct(
        public ?string $provinceId = null,
        public ?string $cityId = null,
        public ?string $postalCode = null,
        public ?GeoPoint $point = null,
    ) {}

    public static function fromCoordinates(?string $provinceId, ?string $cityId, ?string $postalCode, ?float $latitude, ?float $longitude): self
    {
        return new self($provinceId, $cityId, $postalCode,
            $latitude !== null && $longitude !== null ? new GeoPoint($latitude, $longitude) : null);
    }
}
