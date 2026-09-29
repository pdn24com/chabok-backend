<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Application\Dto\FreightMatrixBandDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixCellDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixDraftDto;
use Modules\Pricing\Application\Dto\FreightMatrixTailDraftDto;

/** Normalizes validated matrix-editing input into drafts once, at the application boundary. */
final class FreightMatrixDraftInput
{
    /** @return list<FreightMatrixDraftDto> */
    public static function many(array $matrices): array
    {
        return array_map(self::fromArray(...), array_values($matrices));
    }

    public static function fromArray(array $matrix): FreightMatrixDraftDto
    {
        return new FreightMatrixDraftDto(
            id: (string) $matrix['id'],
            zoneIds: array_values(array_map(strval(...), $matrix['zone_ids'])),
            bands: array_map(self::band(...), array_values($matrix['bands'])),
            serviceOfferingVersionId: $matrix['service_offering_version_id'] ?? null,
            serviceOptionVersionId: $matrix['service_option_version_id'] ?? null,
            originZoneId: $matrix['origin_zone_id'] ?? null,
            linearTail: empty($matrix['linear_tail']) ? null : self::tail($matrix['linear_tail']),
            linearBands: array_key_exists('linear_bands', $matrix) ? array_map(self::band(...), array_values($matrix['linear_bands'])) : null,
        );
    }

    private static function band(array $band): FreightMatrixBandDraftDto
    {
        return new FreightMatrixBandDraftDto(
            id: (string) $band['id'],
            from: $band['from'],
            to: $band['to'] ?? null,
            cells: array_map(self::cell(...), array_values($band['cells'])),
            stepKg: $band['step_kg'] ?? null,
        );
    }

    private static function tail(array $tail): FreightMatrixTailDraftDto
    {
        return new FreightMatrixTailDraftDto(
            id: (string) $tail['id'],
            from: $tail['from'],
            stepKg: $tail['step_kg'],
            cells: array_map(self::cell(...), array_values($tail['cells'])),
        );
    }

    private static function cell(array $cell): FreightMatrixCellDraftDto
    {
        return new FreightMatrixCellDraftDto(
            id: (string) $cell['id'],
            zoneId: (string) $cell['zone_id'],
            state: (string) $cell['state'],
            amount: $cell['amount'] ?? null,
        );
    }
}
