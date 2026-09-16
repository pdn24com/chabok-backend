<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteVersions;

use Modules\Foundation\Application\Data\Page;

final readonly class ListRouteVersionsResult
{
    public function __construct(public Page $data)
    {
    }
}
