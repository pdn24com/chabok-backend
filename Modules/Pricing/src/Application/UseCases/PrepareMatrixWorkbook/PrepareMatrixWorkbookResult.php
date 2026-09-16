<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook;

final readonly class PrepareMatrixWorkbookResult
{
    public function __construct(public array $data)
    {
    }
}
