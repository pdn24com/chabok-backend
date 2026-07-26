<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class LegacyQuoteNormalizer
{
    private const CHARGES = [
        'fld_Manual_Cost' => ['LEGACY_MANUAL_COST', 'Manual cost'],
        'fld_Pack_Cost' => ['LEGACY_PACK_COST', 'Packing cost'],
        'fld_Charge_Cost' => ['LEGACY_CHARGE_COST', 'Charge cost'],
        'fld_Manual_Insurance' => ['LEGACY_MANUAL_INSURANCE', 'Insurance'],
        'fld_Lab_Cost' => ['LEGACY_LAB_COST', 'Laboratory cost'],
        'fld_Agency_Cost_From' => ['LEGACY_AGENCY_COST_FROM', 'Origin agency cost'],
        'fld_Agency_Cost' => ['LEGACY_AGENCY_COST', 'Agency cost'],
        'fld_Manual_VAT' => ['LEGACY_MANUAL_VAT', 'VAT'],
    ];

    /** @param array<string, mixed> $payload
     *  @return list<array<string, mixed>>
     */
    public function normalize(array $payload): array
    {
        if (($payload['result'] ?? null) !== true || ! is_array($payload['objects'] ?? null)) {
            throw new ApiException(
                ApiErrorCode::PricingUnavailable,
                503,
                'Pricing is temporarily unavailable.',
            );
        }
        $options = [];
        foreach ($payload['objects'] as $raw) {
            if (! is_array($raw)) {
                $this->malformed();
            }
            $methodCode = $this->identifier($raw['method_no'] ?? null);
            $methodName = trim((string) ($raw['method_name'] ?? ''));
            if ($methodCode === '' || $methodName === '') {
                $this->malformed();
            }
            if (($raw['result'] ?? null) !== true) {
                $options[] = [
                    'external_method_code' => $methodCode,
                    'method_name' => $methodName,
                    'icon' => $this->nullableString($raw['icon'] ?? null),
                    'external_price_list_code' => null,
                    'zone' => null,
                    'available' => false,
                    'unavailable_reason' => 'Pricing method unavailable.',
                    'currency' => 'IRR',
                    'total_amount' => null,
                    'charge_lines' => [],
                    'min_ins' => null,
                    'delivery_windows' => [],
                ];
                continue;
            }
            if (($raw['currency'] ?? null) !== 'IRR' || ! is_array($raw['price'] ?? null)) {
                $this->malformed();
            }
            $price = $raw['price'];
            $quote = $this->integer($raw['quote'] ?? null);
            $total = $this->integer($price['fld_Total_Cost'] ?? null);
            if ($quote <= 0 || $quote !== $total) {
                $this->malformed();
            }
            $lines = [];
            $sum = 0;
            foreach (self::CHARGES as $field => [$code, $title]) {
                $amount = $this->integer($price[$field] ?? null);
                $sum += $amount;
                if ($amount > 0) {
                    $lines[] = ['charge_code' => $code, 'title' => $title, 'amount' => $amount];
                }
            }
            if ($lines === [] || $sum !== $total) {
                $this->malformed();
            }
            $options[] = [
                'external_method_code' => $methodCode,
                'method_name' => $methodName,
                'icon' => $this->nullableString($raw['icon'] ?? null),
                'external_price_list_code' => $this->nullableIdentifier($price['price_list'] ?? null),
                'zone' => $this->nullableIdentifier($price['zone'] ?? null),
                'available' => true,
                'unavailable_reason' => null,
                'currency' => 'IRR',
                'total_amount' => $total,
                'charge_lines' => $lines,
                'min_ins' => isset($price['min_ins']) ? $this->integer($price['min_ins']) : null,
                'delivery_windows' => $this->deliveryWindows($raw['deliveryTimeWindow'] ?? null),
            ];
        }
        if (array_filter($options, static fn (array $option): bool => $option['available']) === []) {
            throw new ApiException(
                ApiErrorCode::PricingRejected,
                422,
                'No pricing method is available for this Consignment.',
            );
        }

        return $options;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            if ($value < 0) {
                $this->malformed();
            }

            return $value;
        }
        if (is_float($value)) {
            if (! is_finite($value) || $value < 0 || floor($value) !== $value || $value > PHP_INT_MAX) {
                $this->malformed();
            }

            return (int) $value;
        }
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1
            || strlen($value) > 19 || (strlen($value) === 19 && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            $this->malformed();
        }

        return (int) $value;
    }

    private function identifier(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            $this->malformed();
        }
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 120) {
            $this->malformed();
        }

        return $value;
    }

    private function nullableIdentifier(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : $this->identifier($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value) || strlen($value) > 1000) {
            $this->malformed();
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    private function deliveryWindows(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $items = array_is_list($raw) ? $raw : [$raw];
        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $date = $item['gregorian_date'] ?? $item['date'] ?? null;
            if (! is_string($date) || \DateTimeImmutable::createFromFormat('!Y-m-d', $date) === false) {
                continue;
            }
            $ranges = $item['time_ranges'] ?? $item['times'] ?? $item['time'] ?? [];
            $ranges = is_string($ranges) ? [$ranges] : (is_array($ranges) ? $ranges : []);
            $ranges = array_values(array_filter($ranges, static fn ($range): bool => is_string($range) && $range !== ''));
            $normalized[] = [
                'gregorian_date' => $date,
                'jalali_display_date' => $this->snapshot($item['jalali_display_date'] ?? $item['jalali_date'] ?? null),
                'persian_weekday_label' => $this->snapshot($item['weekday'] ?? null),
                'persian_month_label' => $this->snapshot($item['month'] ?? null),
                'time_ranges' => $ranges,
            ];
        }

        return $normalized;
    }

    private function snapshot(mixed $value): ?string
    {
        return is_string($value) && mb_strlen($value) <= 160 ? $value : null;
    }

    private function malformed(): never
    {
        throw new ApiException(
            ApiErrorCode::PricingUnavailable,
            503,
            'Pricing is temporarily unavailable.',
        );
    }
}
