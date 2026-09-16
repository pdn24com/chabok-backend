<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListCities;

use Modules\Foundation\Application\Data\Page;

final readonly class ListCitiesResult
{
    public function __construct(public Page $data)
    {
    }
}
