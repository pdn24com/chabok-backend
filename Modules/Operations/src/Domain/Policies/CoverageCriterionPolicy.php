<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Policies;

use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;

final class CoverageCriterionPolicy
{
    public static function valid(CoverageCriterion $criterion): bool
    {
        return match ($criterion->type) {
            CoverageCriterionType::PROVINCE => $criterion->provinceId !== null,
            CoverageCriterionType::CITY => $criterion->cityId !== null,
            CoverageCriterionType::POSTAL_RANGE => preg_match('/^\\d{10}$/', $criterion->postalFrom ?? '') === 1
                && preg_match('/^\\d{10}$/', $criterion->postalTo ?? '') === 1 && strcmp($criterion->postalFrom, $criterion->postalTo) <= 0,
            CoverageCriterionType::POLYGON => $criterion->geometry !== null,
            CoverageCriterionType::POINT_RADIUS => $criterion->center !== null
                && is_finite($criterion->center->latitude) && $criterion->center->latitude >= -90 && $criterion->center->latitude <= 90
                && is_finite($criterion->center->longitude) && $criterion->center->longitude >= -180 && $criterion->center->longitude <= 180
                && $criterion->radiusMeters !== null && $criterion->radiusMeters >= 1 && $criterion->radiusMeters <= 500000,
        };
    }
}
