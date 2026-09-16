<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompletePickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CompletePickupTaskHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\PickupAccessGuard $pickupAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\PickupTaskReader $pickupTaskReader,
        private \Modules\Operations\Application\Services\PickupTaskGuard $pickupTaskGuard,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Contracts\FrozenCommitmentResolver $commitments,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Operations\Application\ParcelLifecycleService $lifecycle,
        private \Modules\Operations\Application\Repositories\PickupTaskRepository $tasks,
        private \Modules\Operations\Application\Services\PickupTaskRecorder $pickupTaskRecorder,
        private \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskHandler $getPickupTask,
    )
    {
    }

    public function handle(CompletePickupTaskCommand $command): CompletePickupTaskResult
    {
        return new CompletePickupTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        $this->pickupAccessGuard->accessExecution($actor, $nodeId, $id);
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $task = $this->pickupTaskReader->locked($actor, $nodeId, $id);
            $this->pickupTaskGuard->version($task, $expected);
            if (!in_array($task->status, ['ASSIGNED', 'IN_PROGRESS'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Pickup Task cannot be completed in its current state.');
            }
            $consignment = $this->ledger->lockConsignment($actor->hqId, $task->consignment_id);
            $snapshot = json_decode($consignment->commitment_snapshot ?? 'null', true);
            $completedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            $resolution = $snapshot ? $this->commitments->pickupCompleted($snapshot, $completedAt) : null;
            if ($resolution) {
                $end = $resolution['ends_at'] ?? $resolution['computed_at'] ?? null;
                $start = $resolution['starts_at'] ?? $resolution['computed_at'] ?? null;
                $this->ledger->updateConsignment($actor->hqId, $task->consignment_id, [
                    'delivery_commitment_at' => $end ? \Carbon\CarbonImmutable::parse($end)->utc()->format('Y-m-d H:i:s.u') : null,
                    'delivery_commitment_end_at' => $end ? \Carbon\CarbonImmutable::parse($end)->utc()->format('Y-m-d H:i:s.u') : null,
                    'delivery_commitment_start_at' => $start ? \Carbon\CarbonImmutable::parse($start)->utc()->format('Y-m-d H:i:s.u') : null,
                    'delivery_commitment_resolution' => json_encode(['pickup_completed_at' => $completedAt, 'result' => $resolution], JSON_THROW_ON_ERROR),
                ]);
                $this->audit->write($actor->hqId, $actor->userId, 'CONSIGNMENT_COMMITMENT_RESOLVED', 'CONSIGNMENT', (string) $task->consignment_id, $correlationId, after: ['pickup_completed_at' => $completedAt, 'delivery' => $resolution], sourceClient: 'BRANCH_PANEL');
            }
            $this->lifecycle->transition($actor, (string) $task->consignment_id, 'PD', 'PU', 'PICKUP_COMPLETED', null, 'PICKUP_DRIVER', (string) $task->assigned_driver_id, $correlationId, (string) $task->assigned_driver_id);
            $this->tasks->update($id, [
                'status' => 'COMPLETED',
                'version' => $expected + 1,
                'completed_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->pickupTaskRecorder->record($actor, 'PICKUP_TASK_COMPLETED', $id, (string) $task->consignment_id, 'COMPLETED', $correlationId);
        });
        return $this->getPickupTask->handle(new \Modules\Operations\Application\UseCases\GetPickupTask\GetPickupTaskCommand($actor, $nodeId, $id))->data;
    }
}
