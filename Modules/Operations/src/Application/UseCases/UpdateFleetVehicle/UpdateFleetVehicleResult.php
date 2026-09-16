<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetVehicle;

final readonly class UpdateFleetVehicleResult
{
    public function __construct(public array $data)
    {
    }
}
