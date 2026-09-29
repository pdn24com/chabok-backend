<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\RejectManifestException;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestExceptionWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Contracts\ManifestWorkflowGuardInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;

final readonly class RejectManifestExceptionHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestReaderInterface $manifestReader,
        private ConnectionInterface $connection,
        private ManifestWorkflowGuardInterface $manifestWorkflowGuard,
        private ManifestExceptionAccessInterface $manifestExceptionAccess,
        private ClockInterface $clock,
        private ManifestExceptionWriterInterface $manifestExceptionWriter,
        private AuditWriterInterface $auditWriter,
        private ManifestTransitionRecorderInterface $manifestTransitionRecorder,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function handle(RejectManifestExceptionCommand $command): ManifestDetailDto
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
        $this->connection->transaction(function () use ($actor, $node, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId): void {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $manifestVersion);
            $case = $this->manifestWorkflowGuard->lockedPendingCase($actor, $id);
            $this->manifestWorkflowGuard->exceptionVersion($case, $exceptionVersion);
            $this->manifestWorkflowGuard->differentReviewer($actor, $case);
            $next = (int) $case->version + 1;
            $this->manifestExceptionAccess->updateException($case->exception_case_id, [
                'case_status' => ExceptionCaseStatus::Rejected->value,
                'reviewed_by' => $actor->userId,
                'reviewed_at' => $this->clock->now(),
                'decision_note' => $reason,
                'resolution_action' => 'NO_CHANGE',
                'version' => $next,
                'active_pending_slot' => null,
                'updated_at' => $this->clock->now(),
            ]);
            $this->manifestExceptionWriter->exceptionHistory($actor, $case, 'REJECTED', $reason, $manifestVersion + 1, $next);
            $this->manifestRepository->update($id, ['version' => $manifestVersion + 1, 'updated_at' => $this->clock->now()]);
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_REJECTED', 'MANIFEST', $id, $correlationId);
            $this->manifestTransitionRecorder->commandEvent($actor, $id, (string) $case->consignment_id, 'MANIFEST_EXCEPTION_REJECTED', 'REJECTED', $correlationId);
        }, attempts: 3);

        return $this->manifestReader->detail($actor, $node, $id, $context);
    }
}
