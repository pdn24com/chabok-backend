<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListDescendantAreas;

final readonly class ListDescendantAreasHandler
{
    public function __construct(private \Modules\Organization\Application\Repositories\NetworkRepository $network)
    {
    }

    public function handle(ListDescendantAreasCommand $command): ListDescendantAreasResult
    {
        return new ListDescendantAreasResult($this->execute($command->hqId, $command->areaId));
    }

    private function execute(string $hqId, string $areaId): array
    {
        return $this->network->descendantIds($hqId, $areaId);
    }
}
