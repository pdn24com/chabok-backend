<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoveragePolicies;

use Modules\Foundation\Application\Data\Page;

final readonly class ListCoveragePoliciesResult
{
    public function __construct(public Page $data)
    {
    }
}
