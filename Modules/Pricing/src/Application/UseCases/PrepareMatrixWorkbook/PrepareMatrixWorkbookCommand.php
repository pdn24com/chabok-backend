<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PrepareMatrixWorkbookCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public bool $sample)
    {
    }
}
