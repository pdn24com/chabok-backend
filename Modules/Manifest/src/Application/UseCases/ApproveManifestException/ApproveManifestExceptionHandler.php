<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ApproveManifestException;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestExceptionWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Contracts\ManifestRowProcessorInterface;
use Modules\Manifest\Application\Contracts\ManifestWorkflowGuardInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;

final readonly class ApproveManifestExceptionHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestReaderInterface $manifestReader,
        private ConnectionInterface $connection,
        private ManifestWorkflowGuardInterface $manifestWorkflowGuard,
        private ManifestRowProcessorInterface $manifestRowProcessor,
        private ManifestExceptionAccessInterface $manifestExceptionAccess,
        private ClockInterface $clock,
        private ManifestExceptionWriterInterface $manifestExceptionWriter,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function handle(ApproveManifestExceptionCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $node = $command->nodeId;
        $id = $command->id;
        $context = $this->manifestAccessGuard->access($actor, $node, 'manifest.approve');
        $manifestVersion = $command->manifestVersion;
        $exceptionVersion = $command->exceptionVersion;
        $reason = $command->reason;
        $correlationId = $command->correlationId;
        $this->manifestAccessGuard->reviewAccess($actor, $node);
        $result = $this->connection->transaction(function () use ($actor, $node, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId): int {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $manifestVersion);
            $case = $this->manifestWorkflowGuard->lockedPendingCase($actor, $id);
            $this->manifestWorkflowGuard->exceptionVersion($case, $exceptionVersion);
            $this->manifestWorkflowGuard->differentReviewer($actor, $case);
            $success = $this->manifestRowProcessor->applyRows($actor, $node, $m, $correlationId, true);
            if ($success === 0) {
                return 0;
            }
            $next = (int) $case->version + 1;
            $this->manifestExceptionAccess->updateException($case->exception_case_id, [
                'case_status' => ExceptionCaseStatus::Approved->value,
                'reviewed_by' => $actor->userId,
                'reviewed_at' => $this->clock->now(),
                'decision_note' => $reason,
                'resolution_action' => 'ESCALATE',
                'version' => $next,
                'active_pending_slot' => null,
                'updated_at' => $this->clock->now(),
            ]);
            $this->manifestExceptionWriter->exceptionHistory($actor, $case, 'APPROVED', $reason, $manifestVersion + 1, $next);
            $this->manifestRepository->update($id, [
                'state' => ManifestState::Closed->value,
                'approved_by' => $actor->userId,
                'closed_at' => $this->clock->now(),
                'operation_recorded_at' => $this->clock->now(),
                'version' => $manifestVersion + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_APPROVED', 'MANIFEST', $id, $correlationId);
            $this->outboxWriter->write($actor->hqId, 'MANIFEST', $id, 'manifest.closed', $correlationId, [
                'manifest_id' => $id,
                'manifest_status' => (string) $m->manifest_status,
                'succeeded_count' => (string) $success,
            ]);

            return $success;
        }, attempts: 3);
        if ($result === 0) {
            throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'manifest.no_parcel_remains_eligible_exception_approval');
        }

        return $this->manifestReader->detail($actor, $node, $id, $context);
    }
}
