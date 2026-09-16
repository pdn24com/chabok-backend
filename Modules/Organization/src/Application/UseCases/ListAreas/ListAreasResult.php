<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListAreas;

use Modules\Foundation\Application\Data\Page;

final readonly class ListAreasResult
{
    public function __construct(public Page $data)
    {
    }
}
