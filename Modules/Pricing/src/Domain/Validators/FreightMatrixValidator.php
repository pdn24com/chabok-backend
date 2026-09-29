<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Validators;

use Brick\Math\BigDecimal;
use Modules\Pricing\Domain\Enums\MatrixCellState;
use Modules\Pricing\Domain\Enums\MatrixValidationCode;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Support\MatrixQuantityPrecision;
use Modules\Pricing\Domain\ValueObjects\FreightMatrix;
use Modules\Pricing\Domain\ValueObjects\FreightMatrixBand;
use Modules\Pricing\Domain\ValueObjects\MatrixValidationIssue;

final class FreightMatrixValidator
{
    /** @param list<FreightMatrix> $matrices @param list<string> $zoneIds @return list<MatrixValidationIssue> */
    public function validate(
        array $matrices,
        array $zoneIds,
        ?ZonePolicy $policy,
        bool $publishing = false,
    ): array {
        $errors = [];
        $contexts = [];
        $ids = [];
        foreach ($matrices as $index => $matrix) {
            $path = "freight_matrices.{$index}";
            array_push($errors, ...$this->validateDefinition($matrix, $zoneIds, $policy, $path, $ids, $contexts));
            $bands = $this->bandsForValidation($matrix, $path, $publishing, $errors);
            array_push($errors, ...$this->validateBands($bands, $matrix->zoneIds, $path, $publishing, $ids));
        }

        return $errors;
    }

    /** @param list<string> $zoneIds @param array<string,true> $ids @param list<FreightMatrix> $contexts @return list<MatrixValidationIssue> */
    private function validateDefinition(
        FreightMatrix $matrix,
        array $zoneIds,
        ?ZonePolicy $policy,
        string $path,
        array &$ids,
        array &$contexts,
    ): array {
        $errors = [];
        if (isset($ids[$matrix->id])) {
            $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_ID_DUPLICATE, $path.'.id');
        }
        $ids[$matrix->id] = true;
        foreach ($contexts as $context) {
            if ($matrix->hasSameContext($context)) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_CONTEXT_DUPLICATE, $path);
                break;
            }
        }
        $contexts[] = $matrix;
        $missingDirectionalOrigin = $policy === ZonePolicy::DIRECTIONAL && ! in_array($matrix->originZoneId, $zoneIds, true);
        $unexpectedRankedOrigin = $policy === ZonePolicy::HIGHER_ZONE_RANK && ! empty($matrix->originZoneId);
        if ($missingDirectionalOrigin || $unexpectedRankedOrigin) {
            $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_POLICY_MISMATCH, $path.'.origin_zone_id');
        }
        $columns = $matrix->zoneIds;
        if ($columns === [] || count(array_unique($columns)) !== count($columns) || array_diff($columns, $zoneIds)) {
            $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_ZONES_INVALID, $path.'.zone_ids');
        }

        return $errors;
    }

    /** @param list<MatrixValidationIssue> $errors @return list<FreightMatrixBand> */
    private function bandsForValidation(
        FreightMatrix $matrix,
        string $path,
        bool $publishing,
        array &$errors,
    ): array {
        $bands = $matrix->bands;
        if ($publishing && $bands === []) {
            $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_EMPTY, $path.'.bands');
        }
        usort($bands, static fn ($a, $b) => (float) $a->from <=> (float) $b->from);
        $linear = $matrix->linearBands;
        if ($matrix->conflictingLinearDefinitions) {
            $errors[] = new MatrixValidationIssue(MatrixValidationCode::LINEAR_TAIL_INVALID, $path.'.linear_bands');
        }
        $last = $bands === [] ? null : $bands[array_key_last($bands)];
        foreach ($linear as $li => $segment) {
            $step = (float) ($segment->stepKg ?? 0);
            $end = $segment->to;
            $validContinuation = $this->hasValidContinuation($last, $segment);
            $validStep = MatrixQuantityPrecision::isSupported($segment->stepKg) && $step > 0 && $step <= MatrixQuantityPrecision::MAX_LINEAR_STEP;
            $validPosition = $end !== null || $li === array_key_last($linear);
            if (! $validContinuation || ! $validStep || ! $validPosition) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::LINEAR_TAIL_INVALID, $path.'.linear_bands.'.$li);
            }
            $baseStatesByZone = [];
            foreach ($last->cells ?? [] as $baseCell) {
                if (! array_key_exists($baseCell->zoneId, $baseStatesByZone)) {
                    $baseStatesByZone[$baseCell->zoneId] = $baseCell->state;
                }
            }
            foreach ($segment->cells as $cell) {
                if ($cell->state === MatrixCellState::RATE && ($baseStatesByZone[$cell->zoneId] ?? null) !== MatrixCellState::RATE) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::LINEAR_BASE_REQUIRED, $path.'.linear_bands.'.$li.'.cells');
                }
            }
            $bands[] = new FreightMatrixBand($segment->id, $segment->from, $end ?? (float) $segment->from + max(0.0001, $step), $segment->cells, $segment->stepKg);
            $last = $segment;
        }

        return $bands;
    }

    /** @param list<FreightMatrixBand> $bands @param list<string> $columns @param array<string,true> $ids @return list<MatrixValidationIssue> */
    private function validateBands(
        array $bands,
        array $columns,
        string $path,
        bool $publishing,
        array &$ids,
    ): array {
        $errors = [];
        $previousEnd = null;
        foreach ($bands as $bi => $band) {
            $bp = $path.'.bands.'.$bi;
            $from = round((float) $band->from, MatrixQuantityPrecision::DECIMAL_PLACES);
            $to = round((float) $band->to, MatrixQuantityPrecision::DECIMAL_PLACES);
            if (! $this->hasValidRange($band)) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::RANGE_INVALID, $bp);
            }
            if ($previousEnd !== null && $from < $previousEnd) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::RULE_RANGE_OVERLAP, $bp);
            }
            if ($publishing && $previousEnd !== null && $from > $previousEnd) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_RANGE_GAP, $bp);
            }
            $previousEnd = $to;
            if (isset($ids[$band->id])) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_ID_DUPLICATE, $bp.'.id');
            }
            $ids[$band->id] = true;
            $seen = [];
            foreach ($band->cells as $ci => $cell) {
                $cp = $bp.'.cells.'.$ci;
                if (isset($ids[$cell->id]) || isset($seen[$cell->zoneId])) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_ID_DUPLICATE, $cp);
                }
                $ids[$cell->id] = true;
                $seen[$cell->zoneId] = true;
                if (! in_array($cell->zoneId, $columns, true)) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_ZONES_INVALID, $cp);
                }
                if ($cell->state === null) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_STATE_INVALID, $cp);
                }
                if ($cell->state === MatrixCellState::RATE && ! $this->hasValidAmount($cell->amount)) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::BASE_RATE_INVALID, $cp.'.amount');
                }
                if ($cell->state !== MatrixCellState::RATE && ($cell->amount ?? null) !== null) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_STATE_INVALID, $cp);
                }
                if ($publishing && $cell->state === MatrixCellState::EMPTY) {
                    $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_RATE_MISSING, $cp);
                }
            }
            if ($publishing && array_diff($columns, array_keys($seen))) {
                $errors[] = new MatrixValidationIssue(MatrixValidationCode::MATRIX_RATE_MISSING, $bp);
            }
        }

        return $errors;
    }

    private function hasValidContinuation(?FreightMatrixBand $previous, FreightMatrixBand $segment): bool
    {
        if ($previous?->to === null || ! MatrixQuantityPrecision::isSupported($previous->to) || ! MatrixQuantityPrecision::isSupported($segment->from)) {
            return false;
        }

        return MatrixQuantityPrecision::decimal($previous->to)->isEqualTo(MatrixQuantityPrecision::decimal($segment->from));
    }

    private function hasValidRange(FreightMatrixBand $band): bool
    {
        if (! MatrixQuantityPrecision::isSupported($band->from) || ! MatrixQuantityPrecision::isSupported($band->to)) {
            return false;
        }

        $from = MatrixQuantityPrecision::decimal($band->from);
        $to = MatrixQuantityPrecision::decimal($band->to);

        return $from->isPositiveOrZero() && $to->isGreaterThan($from);
    }

    private function hasValidAmount(int|float|string|null $amount): bool
    {
        if ($amount === null || ! is_numeric($amount) || ! is_finite((float) $amount) || (float) $amount <= 0 || (float) $amount > PHP_INT_MAX) {
            return false;
        }

        $rials = BigDecimal::of(trim((string) $amount))->strippedOfTrailingZeros();

        return $rials->getScale() === 0 && $rials->isLessThanOrEqualTo(PHP_INT_MAX);
    }
}
