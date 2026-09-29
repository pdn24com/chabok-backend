<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\ValueObjects;

use Modules\Geography\Domain\ValueObjects\Geometry;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Operations\Domain\Enums\CoverageCriterionType;

final readonly class CoverageCriterion
{
    public function __construct(
        public CoverageCriterionType $type,
        public int $priority,
        public ?string $provinceId = null,
        public ?string $cityId = null,
        public ?string $postalFrom = null,
        public ?string $postalTo = null,
        public ?Geometry $geometry = null,
        public ?GeoPoint $center = null,
        public ?int $radiusMeters = null,
    ) {}
}
