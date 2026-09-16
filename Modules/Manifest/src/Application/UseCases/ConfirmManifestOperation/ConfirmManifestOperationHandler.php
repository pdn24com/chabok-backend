<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ConfirmManifestOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ConfirmManifestOperationHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestOrchestrationAccess $manifestOrchestrationAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestWorkflowGuard $manifestWorkflowGuard,
        private \Modules\Manifest\Application\Services\ManifestExceptionWriter $manifestExceptionWriter,
        private \Modules\Manifest\Application\Services\ManifestRowProcessor $manifestRowProcessor,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(ConfirmManifestOperationCommand $command): ConfirmManifestOperationResult
    {
        $this->execute($command->actor, $command->node, $command->id, $command->expected, $command->correlationId, $command->reasonCode, $command->description);
        return new ConfirmManifestOperationResult();
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $node,
        string $id,
        int $expected,
        string $correlationId,
        ?string $reasonCode = null,
        ?string $description = null,
    ): void
    {
        $this->manifestOrchestrationAccess->access($actor, $node, 'manifest.approve');
        $result = $this->transactions->run(function () use ($actor, $node, $id, $expected, $correlationId, $reasonCode, $description): int {
            $m = $this->manifestWorkflowGuard->lockedManifest($actor, $node, $id);
            $this->manifestWorkflowGuard->manifestVersion($m, $expected);
            if ((string) $m->state !== 'OPEN') {
                throw new ApiException(ApiErrorCode::ManifestNotEditable, 422, 'The Manifest must be Open before confirmation.');
            }
            if (in_array((string) $m->manifest_status, ['NPU', 'NOK'], true)) {
                if (trim((string) $reasonCode) === '' || trim((string) $description) === '') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Exception reason code and description are required.');
                }
                return $this->manifestExceptionWriter->submitException($actor, $node, $m, trim((string) $reasonCode), trim((string) $description), $correlationId);
            }
            if (in_array((string) $m->manifest_status, ['PD', 'OD', 'OS'], true)) {
                $this->manifestOrchestrationAccess->requirePermission($actor, 'driver.view');
                $this->manifestOrchestrationAccess->requirePermission($actor, 'driver.assign');
            }
            $success = $this->manifestRowProcessor->applyRows($actor, $node, $m, $correlationId, false);
            if ($success === 0) {
                $this->workflow->updateManifest($id, ['version' => (int) $m->version + 1, 'updated_at' => $this->clock->now()]);
                return 0;
            }
            $now = $this->clock->now();
            $this->workflow->updateManifest($id, [
                'state' => 'CLOSED',
                'approved_by' => $actor->userId,
                'closed_at' => $now,
                'operation_recorded_at' => $now,
                'version' => (int) $m->version + 1,
                'updated_at' => $now,
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CONFIRMED', 'MANIFEST', $id, $correlationId);
            $this->outbox->write($actor->hqId, 'MANIFEST', $id, 'manifest.closed', $correlationId, [
                'manifest_id' => $id,
                'manifest_status' => (string) $m->manifest_status,
                'succeeded_count' => (string) $success,
            ]);
            return $success;
        });
        if ($result === 0) {
            throw new ApiException(ApiErrorCode::ManifestNoSuccessfulParcels, 422, 'No Parcel can be confirmed.', details: ['current_version' => $expected + 1]);
        }
    }
}
