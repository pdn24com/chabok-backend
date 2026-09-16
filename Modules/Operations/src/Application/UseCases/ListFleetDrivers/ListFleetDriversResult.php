<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetDrivers;

use Modules\Foundation\Application\Data\Page;

final readonly class ListFleetDriversResult
{
    public function __construct(public Page $data)
    {
    }
}
