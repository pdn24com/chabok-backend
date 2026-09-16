<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ApproveManifestExceptionOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ApproveManifestExceptionOperationHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestOrchestrationAccess $manifestOrchestrationAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestWorkflowGuard $manifestWorkflowGuard,
        private \Modules\Manifest\Application\Services\ManifestRowProcessor $manifestRowProcessor,
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Manifest\Application\Services\ManifestExceptionWriter $manifestExceptionWriter,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(ApproveManifestExceptionOperationCommand $command): ApproveManifestExceptionOperationResult
    {
        $this->execute($command->actor, $command->node, $command->id, $command->manifestVersion, $command->exceptionVersion, $command->reason, $command->correlationId);
        return new ApproveManifestExceptionOperationResult();
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        ?string $reason,
        string $correlationId,
    ): void
    {
        $this->manifestOrchestrationAccess->reviewAccess($actor, $node);
        $result = $this->transactions->run(function () use ($actor, $node, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId): int {
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
            $this->exceptionState->updateException($case->exception_case_id, [
                'case_status' => 'APPROVED',
                'reviewed_by' => $actor->userId,
                'reviewed_at' => $this->clock->now(),
                'decision_note' => $reason,
                'resolution_action' => 'ESCALATE',
                'version' => $next,
                'active_pending_slot' => null,
                'updated_at' => $this->clock->now(),
            ]);
            $this->manifestExceptionWriter->exceptionHistory($actor, $case, 'APPROVED', $reason, $manifestVersion + 1, $next);
            $this->workflow->updateManifest($id, [
                'state' => 'CLOSED',
                'approved_by' => $actor->userId,
                'closed_at' => $this->clock->now(),
                'operation_recorded_at' => $this->clock->now(),
                'version' => $manifestVersion + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_APPROVED', 'MANIFEST', $id, $correlationId);
            $this->outbox->write($actor->hqId, 'MANIFEST', $id, 'manifest.closed', $correlationId, [
                'manifest_id' => $id,
                'manifest_status' => (string) $m->manifest_status,
                'succeeded_count' => (string) $success,
            ]);
            return $success;
        });
        if ($result === 0) {
            throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'No Parcel remains eligible for Exception approval.');
        }
    }
}
