<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class HierarchyEditor
{
    public function __construct(
        private \Modules\Organization\Application\Repositories\NetworkRepository $network,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function replaceParent(string $hqId, string $areaId, ?string $parentId): void
    {
        $this->network->removeParent($hqId, $areaId);
        if ($parentId === null) {
            return;
        }
        if ($parentId === $areaId || !$this->network->activeAreaExists($hqId, $parentId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Parent Area is invalid.');
        }
        $cycle = $this->network->wouldCreateCycle($hqId, $areaId, $parentId);
        if ($cycle) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Area hierarchy cycles are not allowed.');
        }
        $this->network->insertHierarchy([
            'area_hierarchy_id' => $this->identifiers->uuid(),
            'hq_id' => $hqId,
            'parent_area_id' => $parentId,
            'child_area_id' => $areaId,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
    }
}
