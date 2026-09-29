<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/** The historical single open-ended tail; newer drafts send `linearBands` instead. */
final class FreightMatrixTailDraftDto
{
    /** @param list<FreightMatrixCellDraftDto> $cells */
    public function __construct(
        public string $id,
        public int|float|string $from,
        public int|float|string $stepKg,
        public array $cells,
    ) {}
}
