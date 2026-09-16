<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CountConsignmentStatusGroupsHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentAccessGuard $consignmentAccessGuard,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
    )
    {
    }

    public function handle(CountConsignmentStatusGroupsCommand $command): CountConsignmentStatusGroupsResult
    {
        return new CountConsignmentStatusGroupsResult($this->execute($command->actor, $command->nodeId, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $filters): array
    {
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');
        return $this->consignments->statusGroupCounts((string) $actor->hqId, $nodeId, $filters);
    }
}
