<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\ListProvinces;

use Modules\Foundation\Application\Data\Page;

final readonly class ListProvincesResult
{
    public function __construct(public Page $data)
    {
    }
}
