<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentAggregateProjectorInterface;
use Modules\Consignment\Application\Dto\AggregateProjectionDto;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Consignment\Application\Repositories\StatusEventRepositoryInterface;
use Modules\Consignment\Domain\Enums\AggregateMode;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ConsignmentAggregateProjector implements ConsignmentAggregateProjectorInterface
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private ClockInterface $clock,
        private ConnectionInterface $connection,
        private ConsignmentRepositoryInterface $consignmentRepository,
        private ParcelRepositoryInterface $parcelRepository,
        private StatusEventRepositoryInterface $statusEventRepository,
    ) {}

    public function project(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $targetStatus,
        ?string $nodeId,
        ?string $manifestId,
        string $reasonCode,
        ?string $driverId = null,
        ?string $correlationId = null,
    ): void {
        $this->projectMany($actor, [$consignmentId], new AggregateProjectionDto($targetStatus, $nodeId, $manifestId, $reasonCode, $driverId, $correlationId));
    }

    /** @param list<string> $consignmentIds */
    public function projectMany(AuthenticatedPrincipal $actor, array $consignmentIds, AggregateProjectionDto $projection): void
    {
        if ($consignmentIds === []) {
            return;
        }
        $this->connection->transaction(fn () => $this->apply($actor, array_values(array_unique($consignmentIds)), $projection));
    }

    /** @param list<string> $consignmentIds */
    private function apply(AuthenticatedPrincipal $actor, array $consignmentIds, AggregateProjectionDto $projection): void
    {
        $consignments = $this->consignmentRepository->lockByIds((string) $actor->hqId, $consignmentIds);
        if ($consignments->count() !== count($consignmentIds)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        // Grouped SQL counts avoid loading every parcel or issuing a query for each parent.
        $countsByConsignment = [];
        $groups = $this->parcelRepository->statusCounts((string) $actor->hqId, $consignmentIds);
        foreach ($groups as $group) {
            $countsByConsignment[$group->consignment_id][$group->current_status] = (int) $group->aggregate_count;
        }
        $sequences = $this->statusEventRepository->sequenceHeads((string) $actor->hqId, $consignmentIds);
        $updates = [];
        $events = [];
        foreach ($consignments as $consignment) {
            $counts = $countsByConsignment[$consignment->consignment_id] ?? [];
            ksort($counts);
            $total = array_sum($counts);
            $targetCount = $counts[$projection->targetStatus] ?? 0;
            $mode = AggregateMode::forCoverage($targetCount, $total)->value;
            $previousCounts = $consignment->parcel_status_counts ?? [];
            ksort($previousCounts);
            $changed = $consignment->current_status !== $projection->targetStatus || ($consignment->aggregate_mode ?? AggregateMode::Full->value) !== $mode || $previousCounts !== $counts;
            if ($changed) {
                $events[] = (new StatusEventRecord)->forceFill([

                    'hq_id' => $actor->hqId,
                    'event_sequence' => (int) ($sequences[$consignment->consignment_id] ?? 0) + 1,
                    'consignment_id' => $consignment->consignment_id,
                    'parcel_id' => null,
                    'previous_status' => $consignment->current_status,
                    'new_status' => $projection->targetStatus,
                    'aggregate_mode' => $mode,
                    'parcel_status_counts' => $counts,
                    'initiator_id' => $actor->userId,
                    'node_id' => $projection->nodeId,
                    'driver_id' => $projection->driverId,
                    'manifest_id' => $projection->manifestId,
                    'correlation_id' => $projection->correlationId,
                    'reason_code' => $projection->reasonCode,
                    'note' => "{$targetCount}/{$total}",
                    'created_at' => $this->clock->now(),
                ])->getAttributes();
            }
            $updates[] = $consignment->forceFill([
                'current_status' => $projection->targetStatus,
                'aggregate_mode' => $mode,
                'parcel_status_counts' => $counts,
                'updated_at' => $this->clock->now(),
            ])->getAttributes();
        }
        foreach (array_chunk($updates, self::BATCH_SIZE) as $batch) {
            $this->consignmentRepository->upsertAggregates($batch);
        }
        foreach (array_chunk($events, self::BATCH_SIZE) as $batch) {
            $this->statusEventRepository->insert($batch);
        }
    }
}
