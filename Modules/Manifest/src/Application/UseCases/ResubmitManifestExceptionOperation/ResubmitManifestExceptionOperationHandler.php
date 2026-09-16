<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ResubmitManifestExceptionOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ResubmitManifestExceptionOperationHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestOrchestrationAccess $manifestOrchestrationAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestWorkflowGuard $manifestWorkflowGuard,
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
        private \Modules\Manifest\Application\Services\ManifestExceptionWriter $manifestExceptionWriter,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Manifest\Application\Services\ManifestTransitionRecorder $manifestTransitionRecorder,
    )
    {
    }

    public function handle(ResubmitManifestExceptionOperationCommand $command): ResubmitManifestExceptionOperationResult
    {
        $this->execute($command->actor, $command->node, $command->id, $command->manifestVersion, $command->exceptionVersion, $command->code, $command->description, $command->correlationId);
        return new ResubmitManifestExceptionOperationResult();
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $code,
        string $description,
        string $correlationId,
    ): void
    {
        $this->manifestOrchestrationAccess->access($actor, $node, 'manifest.edit');
        $this->transactions->run(function () use ($actor, $node, $id, $manifestVersion, $exceptionVersion, $code, $description, $correlationId): void {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $manifestVersion);
            $prior = $this->exceptionState->lockLatestException($actor->hqId, $id);
            if ($prior === null || (string) $prior->case_status !== 'REJECTED') {
                throw new ApiException(ApiErrorCode::ExceptionAlreadyDecided, 422, 'Only a rejected Exception may be resubmitted.');
            }
            $this->manifestWorkflowGuard->exceptionVersion($prior, $exceptionVersion);
            $sequence = (int) $prior->submission_sequence + 1;
            $caseId = $this->manifestExceptionWriter->insertException($actor, $m, $sequence, $code, $description);
            $new = $this->exceptionState->exception($caseId);
            $this->manifestExceptionWriter->exceptionHistory($actor, $new, 'RESUBMITTED', $description, $manifestVersion + 1, 1);
            $this->workflow->updateManifest($id, ['version' => $manifestVersion + 1, 'updated_at' => $this->clock->now()]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_RESUBMITTED', 'MANIFEST', $id, $correlationId);
            $this->manifestTransitionRecorder->commandEvent($actor, $id, (string) $new->consignment_id, 'MANIFEST_EXCEPTION_RESUBMITTED', 'PENDING', $correlationId);
        });
    }
}
