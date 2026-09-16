<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteDefinitions;

use Modules\Foundation\Application\Data\Page;

final readonly class ListRouteDefinitionsResult
{
    public function __construct(public Page $data)
    {
    }
}
