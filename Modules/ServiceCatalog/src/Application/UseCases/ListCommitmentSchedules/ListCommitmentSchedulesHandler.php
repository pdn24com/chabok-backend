<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;

final readonly class ListCommitmentSchedulesHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(ListCommitmentSchedulesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.view');

        $page = $this->commitmentScheduleRepository->paginateIdentities($actor->hqId, $filters->search, $filters->page, $filters->pageSize);

        return $page->through(fn ($schedule) => [
            ...$schedule->attributesToArray(),
            'latest_status' => $schedule->latestVersion?->status,
            'latest_version_number' => $schedule->latestVersion?->version_number,
            'latest_version_id' => $schedule->latestVersion?->commitment_schedule_version_id,
        ]);
    }
}
