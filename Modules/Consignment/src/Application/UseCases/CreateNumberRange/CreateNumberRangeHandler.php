<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateNumberRange;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentNumberRangeDefinitionInterface;
use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CreateNumberRangeHandler
{
    public function __construct(
        private NumberRangeAccessInterface $numberRangeAccess,
        private ConnectionInterface $connection,
        private ConsignmentNumberRangeDefinitionInterface $consignmentNumberRangeDefinition,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private GetNumberRangeHandler $getNumberRangeHandler,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function handle(CreateNumberRangeCommand $command): NumberRangeRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.manage');
        $rangeId = $this->connection->transaction(function () use ($actor, $hqId, $input, $correlationId): string {
            // Serialize all range registrations, across tenants, before testing overlap.
            $this->numberRangeRepository->lockRegistry($this->clock->now());
            $preview = $this->consignmentNumberRangeDefinition->validate($input->definition);
            if ($this->numberRangeRepository->overlaps($preview)) {
                throw new ApiException(ApiErrorCode::ConsignmentNumberRangeOverlap, 409, 'consignment.requested_number_interval_conflicts_with_existing_range');
            }
            $range = new NumberRangeRecord;
            $range->forceFill([

                'hq_id' => $hqId,
                'title' => $input->title,
                'numeric_prefix' => $preview->numericPrefix,
                'total_length' => $preview->totalLength,
                'serial_width' => $preview->serialWidth,
                'serial_start' => $preview->serialStart,
                'serial_end' => $preview->serialEnd,
                'next_serial' => $preview->serialStart,
                'first_number' => $preview->firstNumber,
                'last_number' => $preview->lastNumber,
                'status' => 'AVAILABLE',
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $range->save();
            $rangeId = (string) $range->getKey();
            $this->auditWriter->write($hqId, $actor->userId, 'CONSIGNMENT_NUMBER_RANGE_CREATED', 'CONSIGNMENT_NUMBER_RANGE', $rangeId, $correlationId, after: [
                'status' => 'AVAILABLE',
                'first_number' => $preview->firstNumber,
                'last_number' => $preview->lastNumber,
            ], sourceClient: SourceClient::BranchPanel->value);
            $this->outboxWriter->write($hqId, 'CONSIGNMENT_NUMBER_RANGE', $rangeId, 'consignment.number-range.created', $correlationId, ['range_id' => $rangeId, 'status' => 'AVAILABLE']);

            return $rangeId;
        }, attempts: 3);

        return $this->getNumberRangeHandler->handle(new GetNumberRangeCommand($actor, $rangeId));
    }
}
