<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\RejectManifestExceptionOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RejectManifestExceptionOperationHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestOrchestrationAccess $manifestOrchestrationAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestWorkflowGuard $manifestWorkflowGuard,
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Manifest\Application\Services\ManifestExceptionWriter $manifestExceptionWriter,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Manifest\Application\Services\ManifestTransitionRecorder $manifestTransitionRecorder,
    )
    {
    }

    public function handle(RejectManifestExceptionOperationCommand $command): RejectManifestExceptionOperationResult
    {
        $this->execute($command->actor, $command->node, $command->id, $command->manifestVersion, $command->exceptionVersion, $command->reason, $command->correlationId);
        return new RejectManifestExceptionOperationResult();
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $reason,
        string $correlationId,
    ): void
    {
        $this->manifestOrchestrationAccess->reviewAccess($actor, $node);
        $this->transactions->run(function () use ($actor, $node, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId): void {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $manifestVersion);
            $case = $this->manifestWorkflowGuard->lockedPendingCase($actor, $id);
            $this->manifestWorkflowGuard->exceptionVersion($case, $exceptionVersion);
            $this->manifestWorkflowGuard->differentReviewer($actor, $case);
            $next = (int) $case->version + 1;
            $this->exceptionState->updateException($case->exception_case_id, [
                'case_status' => 'REJECTED',
                'reviewed_by' => $actor->userId,
                'reviewed_at' => $this->clock->now(),
                'decision_note' => $reason,
                'resolution_action' => 'NO_CHANGE',
                'version' => $next,
                'active_pending_slot' => null,
                'updated_at' => $this->clock->now(),
            ]);
            $this->manifestExceptionWriter->exceptionHistory($actor, $case, 'REJECTED', $reason, $manifestVersion + 1, $next);
            $this->workflow->updateManifest($id, ['version' => $manifestVersion + 1, 'updated_at' => $this->clock->now()]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_REJECTED', 'MANIFEST', $id, $correlationId);
            $this->manifestTransitionRecorder->commandEvent($actor, $id, (string) $case->consignment_id, 'MANIFEST_EXCEPTION_REJECTED', 'REJECTED', $correlationId);
        });
    }
}
