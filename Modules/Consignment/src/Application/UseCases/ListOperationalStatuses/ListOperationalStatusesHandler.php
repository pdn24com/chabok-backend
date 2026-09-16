<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatuses;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListOperationalStatusesHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\OperationalStatusAccess $operationalStatusAccess,
        private \Modules\Consignment\Application\Services\OperationalStatusProjection $operationalStatusProjection,
        private \Modules\Consignment\Application\Repositories\OperationalStatusRepository $statuses,
    )
    {
    }

    public function handle(ListOperationalStatusesCommand $command): ListOperationalStatusesResult
    {
        return new ListOperationalStatusesResult($this->execute($command->actor));
    }

    private function execute(AuthenticatedPrincipal $actor): array
    {
        $context = $this->operationalStatusAccess->access($actor, false);
        return array_map(fn($row) => $this->operationalStatusProjection->present((array) $row, $context, $actor), $this->statuses->entries($actor->hqId));
    }
}
