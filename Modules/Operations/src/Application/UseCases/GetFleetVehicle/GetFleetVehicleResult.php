<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetVehicle;

final readonly class GetFleetVehicleResult
{
    public function __construct(public array $data)
    {
    }
}
