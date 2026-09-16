<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableVehicles;

final readonly class ListAvailableVehiclesResult
{
    public function __construct(public array $data)
    {
    }
}
