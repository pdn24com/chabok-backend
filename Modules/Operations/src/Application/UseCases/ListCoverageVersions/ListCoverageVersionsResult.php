<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoverageVersions;

use Modules\Foundation\Application\Data\Page;

final readonly class ListCoverageVersionsResult
{
    public function __construct(public Page $data)
    {
    }
}
