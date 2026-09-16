<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListConsignments;

use Modules\Foundation\Application\Data\Page;

final readonly class ListConsignmentsResult
{
    public function __construct(public Page $data)
    {
    }
}
