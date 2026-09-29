<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class MatrixWorkbookSampleDto
{
    /** @param list<string> $zoneTitles */
    public function __construct(public array $zoneTitles) {}
}
