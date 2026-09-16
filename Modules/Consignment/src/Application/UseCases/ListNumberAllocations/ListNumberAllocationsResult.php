<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberAllocations;

use Modules\Foundation\Application\Data\Page;

final readonly class ListNumberAllocationsResult
{
    public function __construct(public Page $data)
    {
    }
}
