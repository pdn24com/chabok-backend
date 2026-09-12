<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain;

/** Version-owned editing metadata compiled into the existing typed rate-rule engine. */
final class FreightMatrices
{
    /** @return list<array{code:string,field:string}> */
    public function validate(array $matrices, array $zoneIds, string $policy, bool $publishing = false): array
    {
        $errors = []; $contexts = []; $ids = [];
        $error = static function (string $code, string $field) use (&$errors): void { $errors[] = compact('code', 'field'); };
        foreach ($matrices as $mi => $matrix) {
            $path = "freight_matrices.{$mi}";
            if (isset($ids[$matrix['id']])) $error('PRICING_MATRIX_ID_DUPLICATE', $path.'.id');
            $ids[$matrix['id']] = true;
            $context = implode('|', [$matrix['service_offering_version_id'], $matrix['service_option_version_id'] ?? '', $matrix['origin_zone_id'] ?? '']);
            if (isset($contexts[$context])) $error('PRICING_MATRIX_CONTEXT_DUPLICATE', $path);
            $contexts[$context] = true;
            if (($policy === 'DIRECTIONAL' && ! in_array($matrix['origin_zone_id'] ?? null, $zoneIds, true)) || ($policy === 'HIGHER_ZONE_RANK' && ! empty($matrix['origin_zone_id']))) $error('PRICING_MATRIX_POLICY_MISMATCH', $path.'.origin_zone_id');
            $columns = $matrix['zone_ids'];
            if ($columns === [] || count(array_unique($columns)) !== count($columns) || array_diff($columns, $zoneIds)) $error('PRICING_MATRIX_ZONES_INVALID', $path.'.zone_ids');
            $bands = $matrix['bands'];
            if ($publishing && $bands === []) $error('PRICING_MATRIX_EMPTY', $path.'.bands');
            usort($bands, static fn ($a, $b) => (float) $a['from'] <=> (float) $b['from']);
            $tail = $matrix['linear_tail'] ?? null;
            if ($tail !== null) {
                $last = $bands === [] ? null : $bands[array_key_last($bands)];
                $step = (float) ($tail['step_kg'] ?? 0);
                if ($last === null || (float) $tail['from'] !== (float) $last['to'] || ! is_finite($step) || $step <= 0 || abs($step * 10000 - round($step * 10000)) > 0.00001) $error('PRICING_LINEAR_TAIL_INVALID', $path.'.linear_tail');
                foreach ($tail['cells'] as $cell) {
                    $base = array_values(array_filter($last['cells'] ?? [], static fn ($c) => $c['zone_id'] === $cell['zone_id']));
                    if ($cell['state'] === 'RATE' && (($base[0]['state'] ?? null) !== 'RATE')) $error('PRICING_LINEAR_BASE_REQUIRED', $path.'.linear_tail.cells');
                }
                $bands[] = [...$tail, 'to' => (float) $tail['from'] + max(0.0001, $step)];
            }
            $previousEnd = null;
            foreach ($bands as $bi => $band) {
                $bp = $path.'.bands.'.$bi;
                $from = (float) $band['from']; $to = (float) $band['to'];
                if (! is_finite($from) || ! is_finite($to) || $from < 0 || $to <= $from || abs($from * 10000 - round($from * 10000)) > 0.00001 || abs($to * 10000 - round($to * 10000)) > 0.00001) $error('PRICING_RANGE_INVALID', $bp);
                if ($previousEnd !== null && $from < $previousEnd) $error('PRICING_RULE_RANGE_OVERLAP', $bp);
                if ($publishing && $previousEnd !== null && $from > $previousEnd) $error('PRICING_MATRIX_RANGE_GAP', $bp);
                $previousEnd = $to;
                if (isset($ids[$band['id']])) $error('PRICING_MATRIX_ID_DUPLICATE', $bp.'.id');
                $ids[$band['id']] = true;
                $seen = [];
                foreach ($band['cells'] as $ci => $cell) {
                    $cp = $bp.'.cells.'.$ci;
                    if (isset($ids[$cell['id']]) || isset($seen[$cell['zone_id']])) $error('PRICING_MATRIX_ID_DUPLICATE', $cp);
                    $ids[$cell['id']] = true; $seen[$cell['zone_id']] = true;
                    if (! in_array($cell['zone_id'], $columns, true)) $error('PRICING_MATRIX_ZONES_INVALID', $cp);
                    if (! in_array($cell['state'], ['EMPTY', 'RATE', 'UNCOVERED'], true)) $error('PRICING_MATRIX_STATE_INVALID', $cp);
                    if ($cell['state'] === 'RATE' && (! isset($cell['amount']) || ! is_numeric($cell['amount']) || (float) $cell['amount'] != (int) $cell['amount'] || (int) $cell['amount'] <= 0)) $error('PRICING_BASE_RATE_INVALID', $cp.'.amount');
                    if ($cell['state'] !== 'RATE' && ($cell['amount'] ?? null) !== null) $error('PRICING_MATRIX_STATE_INVALID', $cp);
                    if ($publishing && $cell['state'] === 'EMPTY') $error('PRICING_MATRIX_RATE_MISSING', $cp);
                }
                if ($publishing && array_diff($columns, array_keys($seen))) $error('PRICING_MATRIX_RATE_MISSING', $bp);
            }
        }
        return $errors;
    }

    /** @return list<array<string,mixed>> */
    public function compile(array $matrices, string $chargeTypeId): array
    {
        $rules = [];
        foreach ($matrices as $matrix) foreach ($matrix['bands'] as $band) foreach ($band['cells'] as $cell) {
            if ($cell['state'] !== 'RATE') continue;
            $rules[] = [
                'rate_rule_id' => $cell['id'], 'matrix_cell_id' => $cell['id'],
                'service_offering_version_id' => $matrix['service_offering_version_id'],
                'service_option_version_id' => $matrix['service_option_version_id'] ?? null,
                'origin_zone_id' => $matrix['origin_zone_id'] ?? null, 'destination_zone_id' => $cell['zone_id'],
                'charge_type_id' => $chargeTypeId, 'calculation_method' => 'SLAB', 'basis' => 'BILLABLE_WEIGHT',
                'range_from' => $band['from'], 'range_to' => $band['to'], 'fixed_amount' => $cell['amount'],
                'priority' => 10, 'conditions' => [], 'basis_charge_codes' => [],
            ];
        }
        foreach ($matrices as $matrix) {
            $tail = $matrix['linear_tail'] ?? null;
            if ($tail === null) continue;
            $bands = $matrix['bands'];
            usort($bands, static fn ($a, $b) => (float) $a['to'] <=> (float) $b['to']);
            $last = $bands[array_key_last($bands)];
            foreach ($tail['cells'] as $cell) {
                if ($cell['state'] !== 'RATE') continue;
                $base = array_values(array_filter($last['cells'], static fn ($c) => $c['zone_id'] === $cell['zone_id']))[0];
                $rules[] = [
                    'rate_rule_id' => $cell['id'], 'matrix_cell_id' => $cell['id'],
                    'service_offering_version_id' => $matrix['service_offering_version_id'],
                    'service_option_version_id' => $matrix['service_option_version_id'] ?? null,
                    'origin_zone_id' => $matrix['origin_zone_id'] ?? null, 'destination_zone_id' => $cell['zone_id'],
                    'charge_type_id' => $chargeTypeId, 'calculation_method' => 'SLAB', 'basis' => 'BILLABLE_WEIGHT',
                    'range_from' => $tail['from'], 'range_to' => null, 'fixed_amount' => $base['amount'],
                    'unit_rate' => $cell['amount'], 'incremental_step_kg' => $tail['step_kg'],
                    'priority' => 10, 'conditions' => [], 'basis_charge_codes' => [],
                ];
            }
        }
        return $rules;
    }

    /** Complete ranks are required on the whole version, not only the selected pair. */
    public function ranksValid(array $zones): bool
    {
        $ranks = [];
        foreach ($zones as $zone) {
            $rank = $zone['rank'] ?? null;
            if ($rank === null || ! is_numeric($rank) || (int) $rank != $rank || $rank < 1 || isset($ranks[(int) $rank])) return false;
            $ranks[(int) $rank] = true;
        }
        return $zones !== [];
    }
}
