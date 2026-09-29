<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogValidationDocument;
use Modules\ServiceCatalog\Application\Serialization\ScheduleDocument;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class TransitionCommitmentScheduleHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ConnectionInterface $connection,
        private ValidateCommitmentScheduleHandler $validateCommitmentScheduleHandler,
        private ClockInterface $clock,
        private ScheduleReaderInterface $scheduleReader,
        private ScheduleChangeRecorderInterface $scheduleChangeRecorder,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(TransitionCommitmentScheduleCommand $command): CommitmentScheduleVersionRecord
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $action = $command->action;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, $action === 'approve' ? 'service_catalog.approve' : ($action === 'publish' ? 'service_catalog.publish' : 'service_catalog.manage_draft'));

        return $this->connection->transaction(function () use ($actor, $versionId, $action, $correlationId): CommitmentScheduleVersionRecord {
            $row = $this->commitmentScheduleRepository->lockTenantVersion($actor->hqId, $versionId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($action === 'approve') {
                if ((string) $row->status !== VersionLifecycleStatus::Draft->value) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.only_draft_schedule_can_be_approved');
                }
                $validation = $this->validateCommitmentScheduleHandler->handle(new ValidateCommitmentScheduleCommand($actor, $versionId));
                if (! $validation->valid()) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.commitment_schedule_validation_failed', details: CatalogValidationDocument::make($validation));
                }
                $changes = [
                    'status' => VersionLifecycleStatus::Approved->value,
                    'approved_by' => $actor->userId,
                    'approved_at' => $this->clock->now(),
                ];
            } elseif ($action === 'publish') {
                if ((string) $row->status !== VersionLifecycleStatus::Approved->value) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.only_approved_schedule_can_be_published');
                }
                $detail = $this->scheduleReader->versionDetail($actor, $versionId);
                $changes = [
                    'status' => VersionLifecycleStatus::Published->value,
                    'published_by' => $actor->userId,
                    'published_at' => $this->clock->now(),
                    'content_digest' => hash('sha256', json_encode(ScheduleDocument::version($detail), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                ];
            } elseif ($action === 'supersede' && (string) $row->status === VersionLifecycleStatus::Published->value) {
                $changes = ['status' => VersionLifecycleStatus::Superseded->value];
            } elseif ($action === 'archive' && (string) $row->status === VersionLifecycleStatus::Superseded->value) {
                $changes = ['status' => VersionLifecycleStatus::Archived->value];
            } else {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.unsupported_schedule_lifecycle_transition');
            }
            $row->forceFill($changes + ['updated_at' => $this->clock->now()])->save();
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_VERSION_'.mb_strtoupper($action).'D', (string) $row->commitment_schedule_id, $correlationId, ['version_id' => $versionId, 'status' => $changes['status']]);

            return $this->scheduleReader->versionDetail($actor, $versionId);
        }, attempts: 3);
    }
}
