<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidateTariff;

final readonly class ValidateTariffResult
{
    public function __construct(public array $data)
    {
    }
}
