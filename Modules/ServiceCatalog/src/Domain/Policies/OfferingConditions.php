<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Policies;

use Modules\Foundation\Domain\ValueObjects\CoverageAddress;
use Modules\ServiceCatalog\Domain\Enums\EligibilityOperator;
use Modules\ServiceCatalog\Domain\Support\OfferingFacts;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingCondition;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class OfferingConditions
{
    public function coverageMatches(string $referenceType, string $referenceValue, ?string $secondaryReferenceValue, CoverageAddress $party): bool
    {
        return match ($referenceType) {
            'CITY' => ($party->cityId ?? null) === $referenceValue || isset($party->city) && mb_strtolower((string) $party->city) === mb_strtolower((string) $referenceValue),
            'PROVINCE' => ($party->provinceId ?? null) === $referenceValue || isset($party->state) && mb_strtolower((string) $party->state) === mb_strtolower((string) $referenceValue),
            'COUNTRY' => isset($party->country) && mb_strtolower((string) $party->country) === mb_strtolower((string) $referenceValue),
            'POSTAL_RANGE' => isset($party->postalCode) && strcmp((string) $party->postalCode, (string) $referenceValue) >= 0 && strcmp((string) $party->postalCode, (string) $secondaryReferenceValue) <= 0,
            default => false,
        };
    }

    public function conditionPasses(OfferingCondition $condition, OfferingSelectionContext $context): bool
    {
        $actual = OfferingFacts::value($context, $condition->factKey);
        $expected = $condition->expectedValue;

        return match ($condition->operator) {
            EligibilityOperator::Equal => $actual == $expected,
            EligibilityOperator::NotEqual => $actual != $expected,
            EligibilityOperator::In => in_array($actual, (array) $expected, true),
            EligibilityOperator::NotIn => ! in_array($actual, (array) $expected, true),
            EligibilityOperator::Minimum => $actual !== null && (float) $actual >= (float) $expected,
            EligibilityOperator::Maximum => $actual !== null && (float) $actual <= (float) $expected,
            EligibilityOperator::Between => $actual !== null && (float) $actual >= (float) ($expected[0] ?? 0) && (float) $actual <= (float) ($expected[1] ?? 0),
            EligibilityOperator::Exists => $actual !== null,
            EligibilityOperator::NotExists => $actual === null,
            default => false,
        };
    }
}
