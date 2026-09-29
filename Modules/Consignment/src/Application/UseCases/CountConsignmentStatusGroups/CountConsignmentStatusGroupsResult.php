<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups;

final readonly class CountConsignmentStatusGroupsResult
{
    public function __construct(
        public int $total, public int $newRouted, public int $unassigned, public int $assigned,
        public int $inOperation, public int $exception, public int $completed, public int $cancelled,
    ) {}
}
