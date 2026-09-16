<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreateTariff;

final readonly class CreateTariffResult
{
    public function __construct(public array $data)
    {
    }
}
