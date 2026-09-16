<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetConsignmentHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentAccessGuard $consignmentAccessGuard,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
        private \Modules\Consignment\Application\Services\ConsignmentProjection $consignmentProjection,
    )
    {
    }

    public function handle(GetConsignmentCommand $command): GetConsignmentResult
    {
        return new GetConsignmentResult($this->execute($command->actor, $command->nodeId, $command->consignmentId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $consignmentId): array
    {
        $context = $this->consignmentAccessGuard->assertAccess($actor, $nodeId, 'consignment.view');
        $row = $this->consignments->findVisible((string) $actor->hqId, $context['accessible_node_ids'], $consignmentId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->consignmentProjection->detail((array) $row, $context);
    }
}
