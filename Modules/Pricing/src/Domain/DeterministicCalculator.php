<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain;

final class DeterministicCalculator
{
    /**
     * @param list<array<string, mixed>> $rules
     * @param array<string, float|int|bool> $facts
     * @return array{lines:list<array<string,mixed>>,subtotal_amount:int,discount_amount:int,tax_amount:int,total_amount:int,result_fingerprint:string}
     */
    public function calculate(array $rules, array $facts): array
    {
        usort($rules, static fn ($a, $b) => [(int) $a['priority'], (string) $a['charge_type_code'], (float) ($a['range_from'] ?? 0)] <=> [(int) $b['priority'], (string) $b['charge_type_code'], (float) ($b['range_from'] ?? 0)]);
        $lines = []; $slabs = [];
        foreach ($rules as $rule) {
            if (! $this->conditionsPass($rule, $facts)) continue;
            $quantity = $this->quantity((string) $rule['basis'], $facts);
            $from = $rule['range_from'] === null ? null : (float) $rule['range_from'];
            $to = $rule['range_to'] === null ? null : (float) $rule['range_to'];
            $method = (string) $rule['calculation_method'];
            if ($method !== 'TIERED' && (($from !== null && $quantity < $from) || ($to !== null && $quantity >= $to))) continue;
            if ($method === 'SLAB' && isset($slabs[$rule['charge_type_code']])) continue;
            $amount = match ($method) {
                'FIXED' => (int) ($rule['fixed_amount'] ?? 0),
                'PER_UNIT' => $this->money($quantity * (float) ($rule['unit_rate'] ?? 0)),
                'SLAB' => $this->money(($rule['fixed_amount'] ?? null) !== null ? (float) $rule['fixed_amount'] : $quantity * (float) ($rule['unit_rate'] ?? 0)),
                'TIERED' => $this->tierAmount($quantity, $from ?? 0.0, $to, (float) ($rule['unit_rate'] ?? 0)),
                'PERCENT' => $this->percentAmount($rule, $lines, $facts),
                'MIN_MAX' => $this->minMaxAmount($rule, $lines, $facts, $quantity),
                default => 0,
            };
            if ($amount === 0 && $method === 'TIERED') continue;
            if ($method === 'SLAB') $slabs[$rule['charge_type_code']] = true;
            $lines[] = [
                'charge_type_id' => $rule['charge_type_id'], 'rate_rule_id' => $rule['rate_rule_id'],
                'charge_code' => $rule['charge_type_code'], 'title' => $rule['title'] ?? $rule['charge_type_code'],
                'category' => $rule['category'], 'calculation_method' => $method, 'basis' => $rule['basis'],
                'quantity' => round($quantity, 4), 'unit_rate' => $rule['unit_rate'] === null ? null : (float) $rule['unit_rate'],
                'amount' => max(0, $amount), 'accounting_mapping_key' => $rule['accounting_mapping_key'],
                'explanation' => ['range_from' => $from, 'range_to' => $to, 'percentage_bps' => $rule['percentage_bps']],
            ];
        }
        $subtotal = array_sum(array_map(fn ($line) => in_array($line['category'], ['BASE', 'SURCHARGE', 'COMMISSION'], true) ? $line['amount'] : 0, $lines));
        $discount = array_sum(array_map(fn ($line) => $line['category'] === 'DISCOUNT' ? $line['amount'] : 0, $lines));
        $tax = array_sum(array_map(fn ($line) => $line['category'] === 'TAX' ? $line['amount'] : 0, $lines));
        $total = max(0, $subtotal - $discount + $tax);
        $fingerprintLines = array_map(fn ($line) => [$line['charge_code'], $line['rate_rule_id'], $line['quantity'], $line['unit_rate'], $line['amount']], $lines);

        return ['lines' => $lines, 'subtotal_amount' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'total_amount' => $total, 'result_fingerprint' => hash('sha256', json_encode([$fingerprintLines, $subtotal, $discount, $tax, $total], JSON_THROW_ON_ERROR))];
    }

    /** @param array<string,mixed> $rule @param array<string,float|int|bool> $facts */
    private function conditionsPass(array $rule, array $facts): bool
    {
        $conditions = is_string($rule['conditions'] ?? null) ? json_decode($rule['conditions'], true) : ($rule['conditions'] ?? []);
        foreach ((array) $conditions as $key => $expected) if (($facts[$key] ?? null) !== $expected) return false;
        return true;
    }

    /** @param array<string,float|int|bool> $facts */
    private function quantity(string $basis, array $facts): float
    {
        return (float) match ($basis) {
            'ACTUAL_WEIGHT' => $facts['actual_weight_kg'] ?? 0, 'BILLABLE_WEIGHT' => $facts['billable_weight_kg'] ?? 0,
            'PARCEL_COUNT' => $facts['parcel_count'] ?? 0, 'DECLARED_VALUE' => $facts['declared_value_amount'] ?? 0,
            'COD_AMOUNT' => $facts['cod_amount'] ?? 0, default => 1,
        };
    }

    private function tierAmount(float $quantity, float $from, ?float $to, float $rate): int
    {
        $units = max(0.0, min($quantity, $to ?? $quantity) - $from);
        return $this->money($units * $rate);
    }

    /** @param array<string,mixed> $rule @param list<array<string,mixed>> $lines @param array<string,float|int|bool> $facts */
    private function percentAmount(array $rule, array $lines, array $facts): int
    {
        $codes = is_string($rule['basis_charge_codes'] ?? null) ? json_decode($rule['basis_charge_codes'], true) : ($rule['basis_charge_codes'] ?? []);
        $base = $codes ? array_sum(array_map(fn ($line) => in_array($line['charge_code'], $codes, true) ? $line['amount'] : 0, $lines)) : $this->quantity((string) $rule['basis'], $facts);
        return $this->money($base * ((int) ($rule['percentage_bps'] ?? 0)) / 10000);
    }

    /** @param array<string,mixed> $rule @param list<array<string,mixed>> $lines @param array<string,float|int|bool> $facts */
    private function minMaxAmount(array $rule, array $lines, array $facts, float $quantity): int
    {
        $candidate = ($rule['percentage_bps'] ?? null) !== null ? $this->percentAmount($rule, $lines, $facts) : (($rule['unit_rate'] ?? null) !== null ? $this->money($quantity * (float) $rule['unit_rate']) : (int) ($rule['fixed_amount'] ?? 0));
        if ($rule['minimum_amount'] !== null) $candidate = max($candidate, (int) $rule['minimum_amount']);
        if ($rule['maximum_amount'] !== null) $candidate = min($candidate, (int) $rule['maximum_amount']);
        return $candidate;
    }

    private function money(float $amount): int { return (int) floor($amount + 0.5); }
}
