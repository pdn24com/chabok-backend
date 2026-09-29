<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups;

use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;

final readonly class CountConsignmentStatusGroupsHandler
{
    public function __construct(
        private ConsignmentAccessGuardInterface $consignmentAccessGuard,
        private ConsignmentRepositoryInterface $consignmentRepository,
    ) {}

    public function handle(CountConsignmentStatusGroupsCommand $command): CountConsignmentStatusGroupsResult
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $filters = $command->filters;
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');

        // SQL aggregation keeps counts independent of page size without hydrating every consignment.
        $totals = $this->consignmentRepository->statusGroupTotals((string) $actor->hqId, [$nodeId], $filters);

        return new CountConsignmentStatusGroupsResult(
            total: $totals->total,
            newRouted: $totals->newRouted,
            unassigned: $totals->unassigned,
            assigned: $totals->assigned,
            inOperation: $totals->inOperation,
            exception: $totals->exception,
            completed: $totals->completed,
            cancelled: $totals->cancelled,
        );
    }
}
