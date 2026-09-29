<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Policies;

use Modules\Geography\Domain\Policies\GeometryPolicy;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;

final readonly class CoverageMatchPolicy
{
    public function matches(CoverageCriterion $rule, CoverageLocation $location): bool
    {
        return match ($rule->type) {
            CoverageCriterionType::PROVINCE => $location->provinceId === $rule->provinceId,
            CoverageCriterionType::CITY => $location->cityId === $rule->cityId,
            CoverageCriterionType::POSTAL_RANGE => $location->postalCode !== null
                && strcmp($location->postalCode, $rule->postalFrom) >= 0
                && strcmp($location->postalCode, $rule->postalTo) <= 0,
            CoverageCriterionType::POLYGON => $location->point !== null
                && $rule->geometry !== null && GeometryPolicy::contains($rule->geometry, $location->point),
            CoverageCriterionType::POINT_RADIUS => $location->point !== null && $rule->center !== null
                && GeometryPolicy::withinRadius($location->point, $rule->center, $rule->radiusMeters),
        };
    }

    /** Positive means left wins; equal rank must fail as ambiguous. */
    public function compare(CoverageCriterion $left, CoverageCriterion $right): int
    {
        return ($left->priority <=> $right->priority) ?: ($left->type->specificity() <=> $right->type->specificity());
    }
}
