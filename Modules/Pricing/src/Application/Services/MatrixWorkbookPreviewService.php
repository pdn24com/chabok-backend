<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Brick\Math\BigDecimal;
use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;
use Modules\Pricing\Application\Contracts\MatrixWorkbookPreviewServiceInterface;
use Modules\Pricing\Application\Contracts\MatrixWorkbookStorageInterface;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewResultDto;
use Modules\Pricing\Application\Dto\WorkbookFileDto;
use Modules\Pricing\Domain\Enums\MatrixCellState;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Exceptions\InvalidMatrixWorkbook;
use Modules\Pricing\Domain\Validators\FreightMatrixValidator;
use Modules\Pricing\Domain\ValueObjects\FreightMatrix;
use Modules\Pricing\Domain\ValueObjects\FreightMatrixBand;
use Modules\Pricing\Domain\ValueObjects\FreightMatrixCell;

final class MatrixWorkbookPreviewService implements MatrixWorkbookPreviewServiceInterface
{
    private const MAX_CLIENT_INTEGER = 9007199254740991;

    private const FIRST_DATA_ROW = 2;

    public function __construct(
        private FreightMatrixValidator $freightMatrixValidator,
        private MatrixWorkbookStorageInterface $matrixWorkbookStorage,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {}

    public function preview(MatrixWorkbookPreviewDto $input): MatrixWorkbookPreviewResultDto
    {
        $rows = $this->matrixWorkbookStorage->rows($input->contentBase64);
        $header = array_shift($rows);
        if (! $header || $rows === [] || ! str_starts_with(trim($header[0] ?? ''), 'از') || ! str_starts_with(trim($header[1] ?? ''), 'تا')) {
            throw new InvalidMatrixWorkbook('pricing.workbook_first_sheet_structure_is_invalid');
        }
        // Older distributed templates have no step column.
        $zoneColumnOffset = str_starts_with(trim($header[2] ?? ''), 'گام') ? 3 : 2;
        if (count($header) - $zoneColumnOffset !== count($input->matrix->zoneIds)) {
            throw new InvalidMatrixWorkbook('pricing.workbook_zone_column_count_mismatch');
        }
        $bands = [];
        $linearBands = [];
        foreach ($rows as $index => $row) {
            $from = $this->parseLocalizedNumber($row[0] ?? '', $index)->toFloat();
            $end = trim($row[1] ?? '');
            $to = in_array($end, ['', '∞', 'بی نهایت'], true) ? null : $this->parseLocalizedNumber($end, $index)->toFloat();
            $step = null;
            if ($zoneColumnOffset === 3 && trim($row[2] ?? '') !== '') {
                $step = $this->parseLocalizedNumber($row[2], $index)->toFloat();
            }
            $cells = [];
            foreach ($input->matrix->zoneIds as $columnIndex => $zoneId) {
                $raw = trim($row[$zoneColumnOffset + $columnIndex] ?? '');
                $state = match (true) {
                    $raw === '' => MatrixCellState::EMPTY,
                    in_array($raw, ['بدون پوشش', 'UNCOVERED'], true) => MatrixCellState::UNCOVERED,
                    default => MatrixCellState::RATE,
                };
                $amount = $state === MatrixCellState::RATE ? $this->parseLocalizedNumber($raw, $index) : null;
                if ($amount !== null && (! $amount->isPositive() || ! $amount->getFractionalPart()->isZero() || $amount->isGreaterThan(self::MAX_CLIENT_INTEGER))) {
                    throw new InvalidMatrixWorkbook('pricing.workbook_row_rate_must_be_positive_integer', ['row' => $index + self::FIRST_DATA_ROW]);
                }
                // Preview IDs are client editing identities retained when the draft is saved.
                $cells[] = new FreightMatrixCell($this->identifierGenerator->token(), $zoneId, $state, $amount?->toInt());
            }
            $band = new FreightMatrixBand($this->identifierGenerator->token(), $from, $to, $cells, $step);
            if ($step !== null) {
                $linearBands[] = $band;

                continue;
            }
            if ($linearBands !== [] || $to === null) {
                throw new InvalidMatrixWorkbook('pricing.workbook_fixed_range_must_precede_linear');
            }
            $bands[] = $band;
        }
        $preview = new MatrixWorkbookPreviewResultDto($input->matrix, $bands, $linearBands);
        $zoneIds = $input->matrix->zoneIds;
        $policy = ZonePolicy::HIGHER_ZONE_RANK;
        if ($input->matrix->originZoneId !== null) {
            $zoneIds[] = $input->matrix->originZoneId;
            $policy = ZonePolicy::DIRECTIONAL;
        }
        $errors = $this->freightMatrixValidator->validate([
            new FreightMatrix(
                id: $input->matrix->id,
                serviceOfferingVersionId: $input->matrix->serviceOfferingVersionId,
                serviceOptionVersionId: $input->matrix->serviceOptionVersionId,
                originZoneId: $input->matrix->originZoneId,
                zoneIds: $input->matrix->zoneIds,
                bands: $bands,
                linearBands: $linearBands,
            ),
        ], array_values(array_unique($zoneIds)), $policy);
        if ($errors !== []) {
            throw new InvalidMatrixWorkbook('pricing.workbook_ranges_or_steps_are_invalid');
        }

        return $preview;
    }

    /** @param list<string> $titles */
    public function sample(array $titles): WorkbookFileDto
    {
        return $this->matrixWorkbookStorage->sample($titles);
    }

    private function parseLocalizedNumber(string $raw, int $row): BigDecimal
    {
        $value = strtr(trim($raw), array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
        $value = str_replace(['٬', ',', '٫'], ['', '', '.'], $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value) || ! is_finite((float) $value)) {
            throw new InvalidMatrixWorkbook('pricing.workbook_invalid_number_in_row', ['row' => $row + self::FIRST_DATA_ROW]);
        }

        return BigDecimal::of($value);
    }
}
