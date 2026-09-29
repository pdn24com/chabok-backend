<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Pricing\Application\Contracts\FreightMatrixRuleCompilerInterface;
use Modules\Pricing\Domain\Enums\MatrixCellState;
use Modules\Pricing\Domain\Exceptions\InvalidPricingConfiguration;
use Modules\Pricing\Domain\Support\MatrixQuantityPrecision;
use Modules\Pricing\Domain\ValueObjects\CompiledMatrixRateRule;
use Modules\Pricing\Domain\ValueObjects\FreightMatrix;

final class FreightMatrixRuleCompiler implements FreightMatrixRuleCompilerInterface
{
    /** @param list<FreightMatrix> $matrices @return list<CompiledMatrixRateRule> */
    public function compile(array $matrices, string $chargeTypeId): array
    {
        $rules = [];
        foreach ($matrices as $matrix) {
            array_push($rules, ...$this->fixedRules($matrix, $chargeTypeId));
        }
        foreach ($matrices as $matrix) {
            array_push($rules, ...$this->linearRules($matrix, $chargeTypeId));
        }

        return $rules;
    }

    /** @return list<CompiledMatrixRateRule> */
    private function fixedRules(FreightMatrix $matrix, string $chargeTypeId): array
    {
        $rules = [];
        foreach ($matrix->bands as $band) {
            foreach ($band->cells as $cell) {
                if ($cell->state !== MatrixCellState::RATE) {
                    continue;
                }
                $rules[] = new CompiledMatrixRateRule(
                    cellId: $cell->id,
                    serviceOfferingVersionId: $matrix->serviceOfferingVersionId,
                    serviceOptionVersionId: $matrix->serviceOptionVersionId,
                    originZoneId: $matrix->originZoneId,
                    destinationZoneId: $cell->zoneId,
                    chargeTypeId: $chargeTypeId,
                    rangeFrom: $band->from,
                    rangeTo: $band->to,
                    fixedAmount: $cell->amount,
                );
            }
        }

        return $rules;
    }

    /** @return list<CompiledMatrixRateRule> */
    private function linearRules(FreightMatrix $matrix, string $chargeTypeId): array
    {
        $rules = [];
        $linear = $matrix->linearBands;
        if ($linear === []) {
            return [];
        }
        $bands = $matrix->bands;
        usort($bands, static fn ($a, $b) => (float) $a->to <=> (float) $b->to);
        if ($bands === []) {
            throw new InvalidPricingConfiguration('Linear matrix bands require a preceding fixed band.');
        }
        $last = $bands[array_key_last($bands)];
        $bases = [];
        foreach ($last->cells as $cell) {
            if ($cell->state === MatrixCellState::RATE) {
                $bases[$cell->zoneId] = BigDecimal::of(trim((string) $cell->amount))->toInt();
            }
        }
        foreach ($linear as $segment) {
            $nextBases = [];
            foreach ($segment->cells as $cell) {
                if ($cell->state !== MatrixCellState::RATE) {
                    continue;
                }
                if (! isset($bases[$cell->zoneId]) || (float) $segment->stepKg <= 0) {
                    throw new InvalidPricingConfiguration('Linear matrix rates require a preceding rate and a positive step.');
                }
                $base = $bases[$cell->zoneId];
                $rules[] = new CompiledMatrixRateRule(
                    cellId: $cell->id,
                    serviceOfferingVersionId: $matrix->serviceOfferingVersionId,
                    serviceOptionVersionId: $matrix->serviceOptionVersionId,
                    originZoneId: $matrix->originZoneId,
                    destinationZoneId: $cell->zoneId,
                    chargeTypeId: $chargeTypeId,
                    rangeFrom: $segment->from,
                    rangeTo: $segment->to,
                    fixedAmount: $base,
                    unitRate: $cell->amount,
                    incrementalStepKg: $segment->stepKg,
                );
                if ($segment->to !== null) {
                    $distance = MatrixQuantityPrecision::decimal($segment->to)->minus(MatrixQuantityPrecision::decimal($segment->from));
                    $step = MatrixQuantityPrecision::decimal($segment->stepKg);
                    $steps = $distance->dividedBy($step, 0, RoundingMode::Ceiling)->toBigInteger();
                    $nextBases[$cell->zoneId] = $steps
                        ->multipliedBy(BigDecimal::of(trim((string) $cell->amount))->toBigInteger())
                        ->plus($base)
                        ->toInt();
                }
            }
            $bases = $nextBases;
        }

        return $rules;
    }
}
