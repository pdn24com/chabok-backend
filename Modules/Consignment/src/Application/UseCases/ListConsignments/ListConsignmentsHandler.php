<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListConsignments;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListConsignmentsHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentAccessGuard $consignmentAccessGuard,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
    )
    {
    }

    public function handle(ListConsignmentsCommand $command): ListConsignmentsResult
    {
        return new ListConsignmentsResult($this->execute($command->actor, $command->nodeId, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $filters): Page
    {
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');
        return $this->consignments->list((string) $actor->hqId, $nodeId, $filters);
    }
}
