<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewResultDto;
use Modules\Pricing\Domain\ValueObjects\FreightMatrixBand;

/** Serializes editing metadata at the HTTP boundary. */
final class MatrixDraftSerializer
{
    public static function serialize(MatrixWorkbookPreviewResultDto $preview): array
    {
        return [
            'id' => $preview->matrix->id,
            'service_offering_version_id' => $preview->matrix->serviceOfferingVersionId,
            'service_option_version_id' => $preview->matrix->serviceOptionVersionId,
            'origin_zone_id' => $preview->matrix->originZoneId,
            'zone_ids' => $preview->matrix->zoneIds,
            'bands' => array_map(self::band(...), $preview->bands),
            'linear_bands' => array_map(self::band(...), $preview->linearBands),
            'linear_tail' => null,
        ];
    }

    private static function band(FreightMatrixBand $band): array
    {
        $cells = [];
        foreach ($band->cells as $cell) {
            $cells[] = [
                'id' => $cell->id,
                'zone_id' => $cell->zoneId,
                'state' => $cell->state->value,
                'amount' => $cell->amount,
            ];
        }
        $record = [
            'id' => $band->id,
            'from' => $band->from,
            'to' => $band->to,
            'cells' => $cells,
        ];
        if ($band->stepKg !== null) {
            $record['step_kg'] = $band->stepKg;
        }

        return $record;
    }
}
