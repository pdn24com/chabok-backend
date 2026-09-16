<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdateTariffVersion;

final readonly class UpdateTariffVersionResult
{
    public function __construct(public array $data)
    {
    }
}
