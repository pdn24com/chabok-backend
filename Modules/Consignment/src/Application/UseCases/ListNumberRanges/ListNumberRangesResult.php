<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberRanges;

use Modules\Foundation\Application\Data\Page;

final readonly class ListNumberRangesResult
{
    public function __construct(public Page $data)
    {
    }
}
