<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/** A weight band being edited. An open-ended linear band has no upper bound; a stepped band carries its step. */
final class FreightMatrixBandDraftDto
{
    /** @param list<FreightMatrixCellDraftDto> $cells */
    public function __construct(
        public string $id,
        public int|float|string $from,
        public int|float|string|null $to,
        public array $cells,
        public int|float|string|null $stepKg = null,
    ) {}
}
