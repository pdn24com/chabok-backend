<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetVehicle;

final readonly class CreateFleetVehicleResult
{
    public function __construct(public array $data)
    {
    }
}
