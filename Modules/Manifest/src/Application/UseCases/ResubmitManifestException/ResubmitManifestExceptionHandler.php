<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ResubmitManifestException;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestExceptionWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Contracts\ManifestWorkflowGuardInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;

final readonly class ResubmitManifestExceptionHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestReaderInterface $manifestReader,
        private ConnectionInterface $connection,
        private ManifestWorkflowGuardInterface $manifestWorkflowGuard,
        private ManifestExceptionAccessInterface $manifestExceptionAccess,
        private ManifestExceptionWriterInterface $manifestExceptionWriter,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private ManifestTransitionRecorderInterface $manifestTransitionRecorder,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function handle(ResubmitManifestExceptionCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $node = $command->nodeId;
        $id = $command->id;
        $context = $this->manifestAccessGuard->access($actor, $node, 'manifest.edit');
        $manifestVersion = $command->manifestVersion;
        $exceptionVersion = $command->exceptionVersion;
        $code = $command->code;
        $description = $command->description;
        $correlationId = $command->correlationId;
        $this->connection->transaction(function () use ($actor, $node, $id, $manifestVersion, $exceptionVersion, $code, $description, $correlationId): void {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $manifestVersion);
            $prior = $this->manifestExceptionAccess->lockLatestException($actor->hqId, $id);
            if ($prior === null || (string) $prior->case_status !== ExceptionCaseStatus::Rejected->value) {
                throw new ApiException(ApiErrorCode::ExceptionAlreadyDecided, 422, 'manifest.only_rejected_exception_may_be_resubmitted');
            }
            $this->manifestWorkflowGuard->exceptionVersion($prior, $exceptionVersion);
            $sequence = (int) $prior->submission_sequence + 1;
            $caseId = $this->manifestExceptionWriter->insertException($actor, $m, $sequence, $code, $description);
            $new = $this->manifestExceptionAccess->exception($caseId);
            $this->manifestExceptionWriter->exceptionHistory($actor, $new, 'RESUBMITTED', $description, $manifestVersion + 1, 1);
            $this->manifestRepository->update($id, ['version' => $manifestVersion + 1, 'updated_at' => $this->clock->now()]);
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_RESUBMITTED', 'MANIFEST', $id, $correlationId);
            $this->manifestTransitionRecorder->commandEvent($actor, $id, (string) $new->consignment_id, 'MANIFEST_EXCEPTION_RESUBMITTED', 'PENDING', $correlationId);
        }, attempts: 3);

        return $this->manifestReader->detail($actor, $node, $id, $context);
    }
}
