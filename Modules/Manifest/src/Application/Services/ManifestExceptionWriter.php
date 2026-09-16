<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestExceptionWriter
{
    public function __construct(
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
        private \Modules\Manifest\Application\Services\ManifestRowProcessor $manifestRowProcessor,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Manifest\Application\Services\ManifestTransitionRecorder $manifestTransitionRecorder,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function submitException(
        AuthenticatedPrincipal $actor,
        string $node,
        object $m,
        string $code,
        string $description,
        string $correlationId,
    ): int
    {
        if ($this->exceptionState->hasPendingException($actor->hqId, $m->manifest_id)) {
            throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'A pending Exception already exists.');
        }
        $eligible = $this->manifestRowProcessor->eligibleRows($actor, $node, $m);
        if ($eligible === []) {
            return 0;
        }
        $caseId = $this->insertException($actor, $m, 1, $code, $description);
        $case = $this->exceptionState->exception($caseId);
        $this->exceptionHistory($actor, $case, 'SUBMITTED', $description, (int) $m->version + 1, 1);
        $this->workflow->updateManifest($m->manifest_id, ['version' => (int) $m->version + 1, 'updated_at' => $this->clock->now()]);
        $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_SUBMITTED', 'MANIFEST', (string) $m->manifest_id, $correlationId);
        $this->manifestTransitionRecorder->commandEvent($actor, (string) $m->manifest_id, (string) $case->consignment_id, 'MANIFEST_EXCEPTION_SUBMITTED', 'PENDING', $correlationId);
        return count($eligible);
    }

    public function insertException(AuthenticatedPrincipal $actor, object $m, int $sequence, string $code, string $description): string
    {
        $first = $this->workflow->firstManifestParcel($m->manifest_id);
        if ($first === null) {
            throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'The Manifest has no Parcels.');
        }
        $id = $this->identifiers->uuid();
        $this->exceptionState->insertException([
            'exception_case_id' => $id,
            'hq_id' => $actor->hqId,
            'exception_type' => (string) $m->manifest_status,
            'manifest_id' => $m->manifest_id,
            'submission_sequence' => $sequence,
            'consignment_id' => $first->consignment_id,
            'parcel_id' => null,
            'driver_id' => $m->assigned_driver_id,
            'submitted_by' => $actor->userId,
            'reason_code' => $code,
            'description' => $description,
            'case_status' => 'PENDING',
            'version' => 1,
            'active_pending_slot' => hash('sha256', $actor->hqId . '|' . $m->manifest_id . '|PENDING'),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);
        return $id;
    }

    public function exceptionHistory(
        AuthenticatedPrincipal $actor,
        object $case,
        string $action,
        ?string $reason,
        int $manifestVersion,
        int $exceptionVersion,
    ): void
    {
        $this->exceptionState->appendExceptionHistory([
            'exception_history_id' => $this->identifiers->uuid(),
            'hq_id' => $actor->hqId,
            'exception_case_id' => $case->exception_case_id,
            'action' => $action,
            'actor_id' => $actor->userId,
            'safe_note' => $reason,
            'manifest_version' => $manifestVersion,
            'exception_version' => $exceptionVersion,
            'created_at' => $this->clock->now(),
        ]);
    }
}
