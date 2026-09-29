<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\DisableNumberRange;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeCommand;
use Modules\Consignment\Application\UseCases\GetNumberRange\GetNumberRangeHandler;
use Modules\Consignment\Domain\Enums\NumberRangeStatus;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class DisableNumberRangeHandler
{
    public function __construct(
        private NumberRangeAccessInterface $numberRangeAccess,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private GetNumberRangeHandler $getNumberRangeHandler,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function handle(DisableNumberRangeCommand $command): NumberRangeRecord
    {
        $actor = $command->actor;
        $rangeId = $command->rangeId;
        $correlationId = $command->correlationId;
        $hqId = $this->numberRangeAccess->access($actor, 'consignment.number_range.manage');
        $this->connection->transaction(function () use ($actor, $hqId, $rangeId, $correlationId): void {
            $row = $this->numberRangeRepository->lockByTenant($hqId, $rangeId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($row->status !== NumberRangeStatus::Available) {
                throw new ApiException(ApiErrorCode::ConsignmentNumberRangeUnavailable, 422, 'consignment.only_available_range_can_be_disabled');
            }
            $row->forceFill([
                'status' => 'DISABLED',
                'disabled_by' => $actor->userId,
                'disabled_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $row->save();
            $this->auditWriter->write($hqId, $actor->userId, 'CONSIGNMENT_NUMBER_RANGE_DISABLED', 'CONSIGNMENT_NUMBER_RANGE', $rangeId, $correlationId, before: ['status' => 'AVAILABLE'], after: ['status' => 'DISABLED'], sourceClient: SourceClient::BranchPanel->value);
            $this->outboxWriter->write($hqId, 'CONSIGNMENT_NUMBER_RANGE', $rangeId, 'consignment.number-range.disabled', $correlationId, ['range_id' => $rangeId, 'status' => 'DISABLED']);
        }, attempts: 3);

        return $this->getNumberRangeHandler->handle(new GetNumberRangeCommand($actor, $rangeId));
    }
}
