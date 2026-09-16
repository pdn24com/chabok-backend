<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateCommitmentScheduleHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\ServiceCatalog\Application\SchedulePolicy $schedulePolicy,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\ScheduleInput $scheduleInput,
        private \Modules\ServiceCatalog\Application\Services\ScheduleChildrenWriter $scheduleChildrenWriter,
        private \Modules\ServiceCatalog\Application\Services\ScheduleChangeRecorder $scheduleChangeRecorder,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function handle(UpdateCommitmentScheduleCommand $command): UpdateCommitmentScheduleResult
    {
        return new UpdateCommitmentScheduleResult($this->execute($command->actor, $command->versionId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId, array $input, string $correlationId): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if (isset($input['commitment_policy'])) {
            $this->schedulePolicy->validate($input['commitment_policy'], $input['windows'] ?? [], (string) $actor->hqId);
        }
        return $this->transactions->run(function () use ($actor, $versionId, $input, $correlationId): array {
            $row = $this->schedules->lockVersion($actor->hqId, $versionId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((string) $row->status !== 'DRAFT') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only draft schedule versions can be edited.');
            }
            if ((int) $row->lock_version !== (int) $input['expected_version']) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The schedule changed since it was loaded.', details: ['current_version' => (int) $row->lock_version]);
            }
            $this->schedules->rename($actor->hqId, $row->commitment_schedule_id, $input['title'], $this->clock->now());
            $this->schedules->updateVersion($versionId, $this->scheduleInput->versionColumns($input) + ['lock_version' => (int) $row->lock_version + 1, 'updated_at' => $this->clock->now()]);
            $this->scheduleChildrenWriter->replaceChildren($versionId, (string) $actor->hqId, $input);
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_DRAFT_UPDATED', (string) $row->commitment_schedule_id, $correlationId, ['version_id' => $versionId]);
            return $this->scheduleReader->versionDetail($actor, $versionId);
        });
    }
}
