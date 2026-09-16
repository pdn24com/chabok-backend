<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetConsignmentFilterOptionsHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentAccessGuard $consignmentAccessGuard,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
    )
    {
    }

    public function handle(GetConsignmentFilterOptionsCommand $command): GetConsignmentFilterOptionsResult
    {
        return new GetConsignmentFilterOptionsResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');
        return $this->consignments->filterOptions((string) $actor->hqId, $nodeId);
    }
}
