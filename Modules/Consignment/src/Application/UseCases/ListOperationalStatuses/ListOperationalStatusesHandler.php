<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatuses;

use Illuminate\Support\Collection;
use Modules\Consignment\Application\Contracts\OperationalStatusAccessInterface;
use Modules\Consignment\Application\Dto\OperationalStatusViewDto;
use Modules\Consignment\Application\Repositories\OperationalStatusRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;

final readonly class ListOperationalStatusesHandler
{
    public function __construct(
        private OperationalStatusAccessInterface $operationalStatusAccess,
        private OperationalStatusRepositoryInterface $operationalStatusRepository,
    ) {}

    /** @return Collection<int, OperationalStatusViewDto> */
    public function handle(ListOperationalStatusesCommand $command): Collection
    {
        $actor = $command->actor;
        $context = $this->operationalStatusAccess->access($actor, false);
        $tenantManager = $this->operationalStatusAccess->tenantManager($context);

        return $this->operationalStatusRepository->visibleTo($actor->hqId)
            ->map(fn (StatusRecord $status) => new OperationalStatusViewDto($status, $status->hq_id === null ? $context->isPlatformAdmin : $status->hq_id === $actor->hqId && $tenantManager));
    }
}
