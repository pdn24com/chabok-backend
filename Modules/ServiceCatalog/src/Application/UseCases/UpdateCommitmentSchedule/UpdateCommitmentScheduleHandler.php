<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class UpdateCommitmentScheduleHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private SchedulePolicyInterface $schedulePolicy,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private ScheduleInputInterface $scheduleInput,
        private ScheduleChildrenWriterInterface $scheduleChildrenWriter,
        private ScheduleChangeRecorderInterface $scheduleChangeRecorder,
        private ScheduleReaderInterface $scheduleReader,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(UpdateCommitmentScheduleCommand $command): CommitmentScheduleVersionRecord
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if ($input->commitmentPolicy !== null) {
            $this->schedulePolicy->validate($input->commitmentPolicy, $input->windows, (string) $actor->hqId);
        }

        return $this->connection->transaction(function () use ($actor, $versionId, $input, $correlationId): CommitmentScheduleVersionRecord {
            $row = $this->commitmentScheduleRepository->lockTenantVersion($actor->hqId, $versionId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ((string) $row->status !== VersionLifecycleStatus::Draft->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.only_draft_schedule_versions_can_be_edited');
            }
            if ((int) $row->lock_version !== (int) $input->expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'servicecatalog.schedule_changed_since_loaded', details: ['current_version' => (int) $row->lock_version]);
            }
            if ($input->title !== null) {
                $this->commitmentScheduleRepository->updateIdentity($actor->hqId, (string) $row->commitment_schedule_id, ['title' => $input->title, 'updated_at' => $this->clock->now()]);
            }
            $this->commitmentScheduleRepository->applyVersion($row, $this->scheduleInput->versionColumns($input) + ['lock_version' => (int) $row->lock_version + 1, 'updated_at' => $this->clock->now()]);
            $this->scheduleChildrenWriter->replaceChildren($versionId, (string) $actor->hqId, $input);
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_DRAFT_UPDATED', (string) $row->commitment_schedule_id, $correlationId, ['version_id' => $versionId]);

            return $this->scheduleReader->versionDetail($actor, $versionId);
        }, attempts: 3);
    }
}
