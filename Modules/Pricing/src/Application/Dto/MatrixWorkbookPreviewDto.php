<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class MatrixWorkbookPreviewDto
{
    public function __construct(public string $contentBase64, public MatrixDefinitionDto $matrix) {}
}
