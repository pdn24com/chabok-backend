<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class WorkbookFileDto
{
    public function __construct(public string $filename, public string $contentBase64) {}
}
