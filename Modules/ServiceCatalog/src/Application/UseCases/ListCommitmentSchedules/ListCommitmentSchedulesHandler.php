<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListCommitmentSchedulesHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
    )
    {
    }

    public function handle(ListCommitmentSchedulesCommand $command): ListCommitmentSchedulesResult
    {
        return new ListCommitmentSchedulesResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.view');
        return $this->schedules->list((string) $actor->hqId, $filters);
    }
}
