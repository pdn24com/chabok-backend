<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListConsignments;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;

final readonly class ListConsignmentsHandler
{
    public function __construct(
        private ConsignmentAccessGuardInterface $consignmentAccessGuard,
        private ConsignmentRepositoryInterface $consignmentRepository,
    ) {}

    public function handle(ListConsignmentsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $filters = $command->filters;
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');

        return $this->consignmentRepository->paginateVisible((string) $actor->hqId, [$nodeId], $filters);
    }
}
