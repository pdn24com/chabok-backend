<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class TransitionCommitmentScheduleHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler $validateCommitmentSchedule,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
        private \Modules\ServiceCatalog\Application\Services\ScheduleChangeRecorder $scheduleChangeRecorder,
    )
    {
    }

    public function handle(TransitionCommitmentScheduleCommand $command): TransitionCommitmentScheduleResult
    {
        return new TransitionCommitmentScheduleResult($this->execute($command->actor, $command->versionId, $command->action, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId, string $action, string $correlationId): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, $action === 'approve' ? 'service_catalog.approve' : ($action === 'publish' ? 'service_catalog.publish' : 'service_catalog.manage_draft'));
        return $this->transactions->run(function () use ($actor, $versionId, $action, $correlationId): array {
            $row = $this->schedules->lockVersion($actor->hqId, $versionId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ($action === 'approve') {
                if ((string) $row->status !== 'DRAFT') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft schedule can be approved.');
                }
                $validation = $this->validateCommitmentSchedule->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand($actor, $versionId))->data;
                if (!$validation['valid']) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Commitment schedule validation failed.', details: $validation);
                }
                $changes = ['status' => 'APPROVED', 'approved_by' => $actor->userId, 'approved_at' => $this->clock->now()];
            } elseif ($action === 'publish') {
                if ((string) $row->status !== 'APPROVED') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved schedule can be published.');
                }
                $detail = $this->scheduleReader->versionDetail($actor, $versionId);
                $changes = [
                    'status' => 'PUBLISHED',
                    'published_by' => $actor->userId,
                    'published_at' => $this->clock->now(),
                    'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
                ];
            } elseif ($action === 'supersede' && (string) $row->status === 'PUBLISHED') {
                $changes = ['status' => 'SUPERSEDED'];
            } elseif ($action === 'archive' && (string) $row->status === 'SUPERSEDED') {
                $changes = ['status' => 'ARCHIVED'];
            } else {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unsupported schedule lifecycle transition.');
            }
            $this->schedules->updateVersion($versionId, $changes + ['updated_at' => $this->clock->now()]);
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_VERSION_' . mb_strtoupper($action) . 'D', (string) $row->commitment_schedule_id, $correlationId, ['version_id' => $versionId, 'status' => $changes['status']]);
            return $this->scheduleReader->versionDetail($actor, $versionId);
        });
    }
}
