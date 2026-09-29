<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookSampleDto;

final readonly class PrepareMatrixWorkbookCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public MatrixWorkbookPreviewDto|MatrixWorkbookSampleDto $input,
    ) {}
}
