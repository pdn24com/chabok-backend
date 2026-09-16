<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListNodes;

use Modules\Foundation\Application\Data\Page;

final readonly class ListNodesResult
{
    public function __construct(public Page $data)
    {
    }
}
