<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestExceptionWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestRowProcessorInterface;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

final readonly class ManifestExceptionWriter implements ManifestExceptionWriterInterface
{
    public function __construct(
        private ManifestExceptionAccessInterface $manifestExceptionAccess,
        private ManifestRowProcessorInterface $manifestRowProcessor,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private ManifestTransitionRecorderInterface $manifestTransitionRecorder,
        private ManifestRepositoryInterface $manifestRepository,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
    ) {}

    public function submitException(
        AuthenticatedPrincipal $actor,
        string $node,
        ManifestRecord $m,
        string $code,
        string $description,
        string $correlationId,
    ): int {
        if ($this->manifestExceptionAccess->hasPendingException($actor->hqId, $m->manifest_id)) {
            throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'manifest.pending_exception_already_exists');
        }
        $eligible = $this->manifestRowProcessor->eligibleRows($actor, $node, $m);
        if ($eligible === []) {
            return 0;
        }
        $caseId = $this->insertException($actor, $m, 1, $code, $description);
        $case = $this->manifestExceptionAccess->exception($caseId);
        $this->exceptionHistory($actor, $case, 'SUBMITTED', $description, (int) $m->version + 1, 1);
        $this->manifestRepository->update((string) $m->manifest_id, ['version' => (int) $m->version + 1, 'updated_at' => $this->clock->now()]);
        $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_EXCEPTION_SUBMITTED', 'MANIFEST', (string) $m->manifest_id, $correlationId);
        $this->manifestTransitionRecorder->commandEvent($actor, (string) $m->manifest_id, (string) $case->consignment_id, 'MANIFEST_EXCEPTION_SUBMITTED', 'PENDING', $correlationId);

        return count($eligible);
    }

    public function insertException(
        AuthenticatedPrincipal $actor,
        ManifestRecord $m,
        int $sequence,
        string $code,
        string $description,
    ): string {
        $first = $this->manifestParcelRepository->firstParcelOf((string) $m->manifest_id);
        if ($first === null) {
            throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'manifest.manifest_has_no_parcels');
        }
        $id = $this->manifestExceptionAccess->insertException([

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
            'case_status' => ExceptionCaseStatus::Pending->value,
            'version' => 1,
            'active_pending_slot' => hash('sha256', $actor->hqId.'|'.$m->manifest_id.'|PENDING'),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        return $id;
    }

    public function exceptionHistory(
        AuthenticatedPrincipal $actor,
        OperationalExceptionCaseRecord $case,
        string $action,
        ?string $reason,
        int $manifestVersion,
        int $exceptionVersion,
    ): void {
        $this->manifestExceptionAccess->appendExceptionHistory([

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
