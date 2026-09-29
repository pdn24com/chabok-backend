<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewResultDto;
use Modules\Pricing\Application\Dto\WorkbookFileDto;

interface MatrixWorkbookPreviewServiceInterface
{
    public function preview(MatrixWorkbookPreviewDto $input): MatrixWorkbookPreviewResultDto;

    /** @param list<string> $titles */
    public function sample(array $titles): WorkbookFileDto;
}
