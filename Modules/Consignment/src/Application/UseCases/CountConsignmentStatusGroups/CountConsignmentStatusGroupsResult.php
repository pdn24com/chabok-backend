<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups;

final readonly class CountConsignmentStatusGroupsResult
{
    public function __construct(public array $data)
    {
    }
}
