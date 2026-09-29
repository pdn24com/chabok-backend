<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Application\Dto\FreightMatrixBandDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixCellDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixTailDraftDto;

/**
 * Writes matrix drafts back in the stored JSON shape. `linear_tail` and `linear_bands` keys appear only when the
 * draft actually holds them, because the saved metadata reader tells those two representations apart by key.
 */
final class FreightMatrixDraftDocument
{
    /** @param list<FreightMatrixDraftDto> $matrices @return list<array<string, mixed>> */
    public static function many(array $matrices): array
    {
        return array_map(self::serialize(...), $matrices);
    }

    /** @return array<string, mixed> */
    public static function serialize(FreightMatrixDraftDto $matrix): array
    {
        return [
            'id' => $matrix->id,
            'service_offering_version_id' => $matrix->serviceOfferingVersionId,
            'service_option_version_id' => $matrix->serviceOptionVersionId,
            'origin_zone_id' => $matrix->originZoneId,
            'zone_ids' => $matrix->zoneIds,
            'bands' => array_map(self::band(...), $matrix->bands),
            ...$matrix->linearTail === null ? [] : ['linear_tail' => self::tail($matrix->linearTail)],
            ...$matrix->linearBands === null ? [] : ['linear_bands' => array_map(self::band(...), $matrix->linearBands)],
        ];
    }

    /** @return array<string, mixed> */
    private static function band(FreightMatrixBandDraftDto $band): array
    {
        return [
            'id' => $band->id,
            'from' => $band->from,
            'to' => $band->to,
            'cells' => array_map(self::cell(...), $band->cells),
            ...$band->stepKg === null ? [] : ['step_kg' => $band->stepKg],
        ];
    }

    /** @return array<string, mixed> */
    private static function tail(FreightMatrixTailDraftDto $tail): array
    {
        return [
            'id' => $tail->id,
            'from' => $tail->from,
            'step_kg' => $tail->stepKg,
            'cells' => array_map(self::cell(...), $tail->cells),
        ];
    }

    /** @return array<string, mixed> */
    private static function cell(FreightMatrixCellDraftDto $cell): array
    {
        return [
            'id' => $cell->id,
            'zone_id' => $cell->zoneId,
            'state' => $cell->state,
            'amount' => $cell->amount,
        ];
    }
}
