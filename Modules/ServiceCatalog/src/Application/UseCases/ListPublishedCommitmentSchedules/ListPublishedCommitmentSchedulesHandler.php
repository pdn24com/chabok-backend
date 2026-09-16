<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPublishedCommitmentSchedulesHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function handle(ListPublishedCommitmentSchedulesCommand $command): ListPublishedCommitmentSchedulesResult
    {
        return new ListPublishedCommitmentSchedulesResult($this->execute($command->actor, $command->includeVersionIds));
    }

    private function execute(AuthenticatedPrincipal $actor, array $includeVersionIds = []): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.view');
        $includeVersionIds = array_values(array_unique(array_map('strval', $includeVersionIds)));
        $includeVersionIds = array_map(fn($id) => $this->schedules->latestVersionId($id) ?? $id, $includeVersionIds);
        return array_map(fn($row) => $this->scheduleReader->versionDetail($actor, (string) $row->commitment_schedule_version_id), $this->schedules->published($actor->hqId, $includeVersionIds));
    }
}
