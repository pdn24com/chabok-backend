<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection;

final readonly class ValidateServiceSelectionResult
{
    public function __construct(public array $data)
    {
    }
}
