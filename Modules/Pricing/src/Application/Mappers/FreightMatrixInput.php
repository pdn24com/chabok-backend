<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Application\Dto\FreightMatrixBandDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixCellDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixDraftDto;
use Modules\Pricing\Domain\Enums\MatrixCellState;
use Modules\Pricing\Domain\ValueObjects\FreightMatrix;
use Modules\Pricing\Domain\ValueObjects\FreightMatrixBand;
use Modules\Pricing\Domain\ValueObjects\FreightMatrixCell;

/** Converts validated editing input and saved legacy metadata at the application boundary. */
final class FreightMatrixInput
{
    /** @return list<FreightMatrix> */
    public static function many(array $matrices): array
    {
        return array_map(self::fromArray(...), $matrices);
    }

    /** @param list<FreightMatrixDraftDto> $drafts @return list<FreightMatrix> */
    public static function fromDrafts(array $drafts): array
    {
        return array_map(self::fromDraft(...), $drafts);
    }

    public static function fromDraft(FreightMatrixDraftDto $matrix): FreightMatrix
    {
        $tail = $matrix->linearTail;
        $linear = $matrix->linearBands ?? ($tail === null ? [] : [new FreightMatrixBandDraftDto($tail->id, $tail->from, null, $tail->cells, $tail->stepKg)]);

        return new FreightMatrix(
            id: $matrix->id,
            serviceOfferingVersionId: $matrix->serviceOfferingVersionId,
            serviceOptionVersionId: $matrix->serviceOptionVersionId,
            originZoneId: $matrix->originZoneId,
            zoneIds: $matrix->zoneIds,
            bands: array_map(self::draftBand(...), $matrix->bands),
            linearBands: array_map(self::draftBand(...), $linear),
            conflictingLinearDefinitions: $matrix->linearBands !== null && $tail !== null,
        );
    }

    public static function fromArray(array $matrix): FreightMatrix
    {
        $linear = $matrix['linear_bands'] ?? (empty($matrix['linear_tail']) ? [] : [[...$matrix['linear_tail'], 'to' => null]]);

        return new FreightMatrix(
            id: $matrix['id'],
            serviceOfferingVersionId: $matrix['service_offering_version_id'] ?? null,
            serviceOptionVersionId: $matrix['service_option_version_id'] ?? null,
            originZoneId: $matrix['origin_zone_id'] ?? null,
            zoneIds: $matrix['zone_ids'],
            bands: array_map(self::band(...), $matrix['bands']),
            linearBands: array_map(self::band(...), $linear),
            conflictingLinearDefinitions: array_key_exists('linear_bands', $matrix) && ! empty($matrix['linear_tail']),
        );
    }

    private static function draftBand(FreightMatrixBandDraftDto $band): FreightMatrixBand
    {
        return new FreightMatrixBand(
            id: $band->id,
            from: $band->from,
            to: $band->to,
            cells: array_map(self::draftCell(...), $band->cells),
            stepKg: $band->stepKg,
        );
    }

    private static function draftCell(FreightMatrixCellDraftDto $cell): FreightMatrixCell
    {
        return new FreightMatrixCell(
            id: $cell->id,
            zoneId: $cell->zoneId,
            state: MatrixCellState::tryFrom($cell->state),
            amount: $cell->amount,
        );
    }

    private static function band(array $band): FreightMatrixBand
    {
        return new FreightMatrixBand(
            id: $band['id'],
            from: $band['from'],
            to: $band['to'],
            cells: array_map(self::cell(...), $band['cells']),
            stepKg: $band['step_kg'] ?? null,
        );
    }

    private static function cell(array $cell): FreightMatrixCell
    {
        return new FreightMatrixCell(
            id: $cell['id'],
            zoneId: $cell['zone_id'],
            state: MatrixCellState::tryFrom($cell['state']),
            amount: $cell['amount'] ?? null,
        );
    }
}
