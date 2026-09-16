<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\AddAreaEdge;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AddAreaEdgeHandler
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function handle(AddAreaEdgeCommand $command): AddAreaEdgeResult
    {
        $this->execute($command->hqId, $command->parentAreaId, $command->childAreaId);
        return new AddAreaEdgeResult();
    }

    private function execute(string $hqId, string $parentAreaId, string $childAreaId): void
    {
        $this->transactions->run(function () use ($hqId, $parentAreaId, $childAreaId): void {
            $this->network->lockAreas($hqId, [$parentAreaId, $childAreaId]);
            $count = $this->network->countAreas($hqId, [$parentAreaId, $childAreaId]);
            if ($count !== 2) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $cycle = $this->network->wouldCreateCycle($hqId, $childAreaId, $parentAreaId);
            if ($parentAreaId === $childAreaId || $cycle) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Area hierarchy cycles are not allowed.');
            }
            $this->network->insertHierarchy([
                'area_hierarchy_id' => $this->identifiers->uuid(),
                'hq_id' => $hqId,
                'parent_area_id' => $parentAreaId,
                'child_area_id' => $childAreaId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        });
    }
}
