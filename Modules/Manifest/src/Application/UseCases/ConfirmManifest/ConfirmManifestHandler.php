<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ConfirmManifest;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestExceptionWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Contracts\ManifestRowProcessorInterface;
use Modules\Manifest\Application\Contracts\ManifestWorkflowGuardInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestState;

final readonly class ConfirmManifestHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestReaderInterface $manifestReader,
        private ConnectionInterface $connection,
        private ManifestWorkflowGuardInterface $manifestWorkflowGuard,
        private ManifestExceptionWriterInterface $manifestExceptionWriter,
        private ManifestRowProcessorInterface $manifestRowProcessor,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function handle(ConfirmManifestCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $node = $command->nodeId;
        $id = $command->id;
        $context = $this->manifestAccessGuard->access($actor, $node, 'manifest.approve');
        $expected = $command->expected;
        $correlationId = $command->correlationId;
        $reasonCode = $command->reasonCode;
        $description = $command->description;
        $result = $this->connection->transaction(function () use ($actor, $node, $id, $expected, $correlationId, $reasonCode, $description): int {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $expected);
            if ($m->state !== ManifestState::Open) {
                throw new ApiException(ApiErrorCode::ManifestNotEditable, 422, 'manifest.manifest_must_be_open_before_confirmation');
            }
            if (in_array((string) $m->manifest_status, ['NPU', 'NOK'], true)) {
                if (trim((string) $reasonCode) === '' || trim((string) $description) === '') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'manifest.exception_reason_code_description_are_required');
                }

                return $this->manifestExceptionWriter->submitException($actor, $node, $m, trim((string) $reasonCode), trim((string) $description), $correlationId);
            }
            if (in_array((string) $m->manifest_status, ['PD', 'OD', 'OS'], true)) {
                $this->manifestAccessGuard->requirePermission($actor, 'driver.view');
                $this->manifestAccessGuard->requirePermission($actor, 'driver.assign');
            }
            $success = $this->manifestRowProcessor->applyRows($actor, $node, $m, $correlationId, false);
            if ($success === 0) {
                $this->manifestRepository->update($id, ['version' => (int) $m->version + 1, 'updated_at' => $this->clock->now()]);

                return 0;
            }
            $now = $this->clock->now();
            $this->manifestRepository->update($id, [
                'state' => ManifestState::Closed->value,
                'approved_by' => $actor->userId,
                'closed_at' => $now,
                'operation_recorded_at' => $now,
                'version' => (int) $m->version + 1,
                'updated_at' => $now,
            ]);
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_CONFIRMED', 'MANIFEST', $id, $correlationId);
            $this->outboxWriter->write($actor->hqId, 'MANIFEST', $id, 'manifest.closed', $correlationId, [
                'manifest_id' => $id,
                'manifest_status' => (string) $m->manifest_status,
                'succeeded_count' => (string) $success,
            ]);

            return $success;
        }, attempts: 3);
        if ($result === 0) {
            throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'manifest.no_parcel_can_be_confirmed', details: ['current_version' => $expected + 1]);
        }

        return $this->manifestReader->detail($actor, $node, $id, $context);
    }
}
