<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class CloneCommitmentScheduleHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private ScheduleChangeRecorderInterface $scheduleChangeRecorder,
        private ScheduleReaderInterface $scheduleReader,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(CloneCommitmentScheduleCommand $command): CommitmentScheduleVersionRecord
    {
        $actor = $command->actor;
        $identityId = $command->identityId;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');

        return $this->connection->transaction(function () use ($actor, $identityId, $correlationId): CommitmentScheduleVersionRecord {
            // Stable identity lock prevents competing clones from creating two draft successors.
            $identity = $this->commitmentScheduleRepository->lockIdentity($actor->hqId, $identityId);
            if ($identity === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($this->commitmentScheduleRepository->hasUnpublishedSuccessor($identity)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'pricing.unpublished_successor_already_exists');
            }
            $previous = $this->commitmentScheduleRepository->lockLatestVersionWithChildren($identity);
            if ($previous === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $previousId = $previous->commitment_schedule_version_id;
            $newId = $this->commitmentScheduleRepository->replicateAsDraft($previous, [
                'previous_version_id' => $previousId,
                'version_number' => $previous->version_number + 1, 'status' => VersionLifecycleStatus::Draft->value, 'lock_version' => 1,
                'valid_from' => null, 'valid_to' => null, 'created_by' => $actor->userId,
                'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_DRAFT_CLONED', $identityId, $correlationId, ['version_id' => $newId, 'previous_version_id' => $previousId]);

            return $this->scheduleReader->versionDetail($actor, $newId);
        }, attempts: 3);
    }
}
