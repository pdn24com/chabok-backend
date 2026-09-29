<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Domain\ValueObjects\FreightMatrixBand;

final readonly class MatrixWorkbookPreviewResultDto
{
    /** @param list<FreightMatrixBand> $bands @param list<FreightMatrixBand> $linearBands */
    public function __construct(
        public MatrixDefinitionDto $matrix,
        public array $bands,
        public array $linearBands,
    ) {}
}
