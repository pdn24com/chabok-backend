<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetVehicles;

use Modules\Foundation\Application\Data\Page;

final readonly class ListFleetVehiclesResult
{
    public function __construct(public Page $data)
    {
    }
}
