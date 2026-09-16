<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain;

final readonly class PricingRulePolicy
{
    public function hasAmbiguousRuleRanges(array $rules): bool
    {
        foreach ($rules as $leftIndex => $left) {
            foreach (array_slice($rules, $leftIndex + 1) as $right) {
                $selector = [
                    'service_offering_version_id',
                    'service_option_version_id',
                    'origin_zone_id',
                    'destination_zone_id',
                    'charge_type_id',
                    'priority',
                    'basis',
                ];
                if (array_filter($selector, fn($field) => ($left[$field] ?? null) !== ($right[$field] ?? null))) {
                    continue;
                }
                $leftFrom = $left['range_from'] === null ? -INF : (float) $left['range_from'];
                $leftTo = $left['range_to'] === null ? INF : (float) $left['range_to'];
                $rightFrom = $right['range_from'] === null ? -INF : (float) $right['range_from'];
                $rightTo = $right['range_to'] === null ? INF : (float) $right['range_to'];
                if (max($leftFrom, $rightFrom) < min($leftTo, $rightTo)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function memberPrecedence(string $type): int
    {
        return match ($type) {
            'EXPLICIT_OVERRIDE' => 400,
            'POSTAL_RANGE' => 300,
            'POLYGON' => 250,
            'CITY' => 200,
            'PROVINCE' => 100,
            default => 0,
        };
    }
}
