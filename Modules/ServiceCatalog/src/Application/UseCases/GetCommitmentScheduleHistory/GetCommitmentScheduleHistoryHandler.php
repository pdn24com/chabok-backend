<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetCommitmentScheduleHistoryHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function handle(GetCommitmentScheduleHistoryCommand $command): GetCommitmentScheduleHistoryResult
    {
        return new GetCommitmentScheduleHistoryResult($this->execute($command->actor, $command->identityId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $identityId): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.history.view');
        if (!$this->schedules->identityExists($actor->hqId, $identityId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return array_map(fn($id) => $this->scheduleReader->versionDetail($actor, (string) $id), $this->schedules->history($identityId));
    }
}
