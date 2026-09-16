<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain;

final readonly class OfferingConditions
{
    public function coverageMatches(array $reference, array $party): bool
    {
        return match ($reference['reference_type']) {
            'CITY' => ($party['city_id'] ?? null) === $reference['reference_value'] || isset($party['city']) && mb_strtolower((string) $party['city']) === mb_strtolower((string) $reference['reference_value']),
            'PROVINCE' => ($party['province_id'] ?? null) === $reference['reference_value'] || isset($party['state']) && mb_strtolower((string) $party['state']) === mb_strtolower((string) $reference['reference_value']),
            'COUNTRY' => isset($party['country']) && mb_strtolower((string) $party['country']) === mb_strtolower((string) $reference['reference_value']),
            'POSTAL_RANGE' => isset($party['postal_code']) && strcmp((string) $party['postal_code'], (string) $reference['reference_value']) >= 0 && strcmp((string) $party['postal_code'], (string) $reference['secondary_reference_value']) <= 0,
            default => false,
        };
    }

    public function conditionPasses(array $condition, array $context): bool
    {
        $actual = \Modules\ServiceCatalog\Domain\OfferingFacts::value($context, (string) ($condition['fact_key'] ?? ''));
        $expected = $condition['expected_value'] ?? null;
        return match ($condition['operator'] ?? 'EQ') {
            'EQ' => $actual == $expected,
            'NEQ' => $actual != $expected,
            'IN' => in_array($actual, (array) $expected, true),
            'NOT_IN' => !in_array($actual, (array) $expected, true),
            'MIN' => $actual !== null && (float) $actual >= (float) $expected,
            'MAX' => $actual !== null && (float) $actual <= (float) $expected,
            'BETWEEN' => $actual !== null && (float) $actual >= (float) ($expected[0] ?? 0) && (float) $actual <= (float) ($expected[1] ?? 0),
            'EXISTS' => $actual !== null,
            'NOT_EXISTS' => $actual === null,
            default => false,
        };
    }
}
