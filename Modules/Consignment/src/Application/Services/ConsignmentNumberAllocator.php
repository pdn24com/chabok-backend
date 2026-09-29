<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentNumberAllocatorInterface;
use Modules\Consignment\Application\Dto\AllocatedConsignmentNumberDto;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Consignment\Domain\Support\DecimalString;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberAllocationRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final class ConsignmentNumberAllocator implements ConsignmentNumberAllocatorInterface
{
    public function __construct(
        private ClockInterface $clock,
        private NumberRangeRepositoryInterface $numberRangeRepository,
    ) {}

    public function next(string $hqId): AllocatedConsignmentNumberDto
    {
        $range = $this->numberRangeRepository->lockNextAllocatable($hqId);
        if ($range === null) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberRangeExhausted, 422, 'consignment.no_consignment_number_range_has_remaining_capacity');
        }
        $serial = (string) $range->next_serial;
        $number = (string) $range->numeric_prefix.$serial;
        if (strlen($number) !== (int) $range->total_length || ! preg_match('/^[0-9]+$/D', $number)) {
            throw new ApiException(ApiErrorCode::InternalServerError, 500, 'consignment.unable_allocate_consignment_number');
        }
        $last = strcmp($serial, (string) $range->serial_end) === 0;
        $range->forceFill($last ? [
            'next_serial' => null,
            'status' => 'EXHAUSTED',
            'exhausted_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ] : [
            'next_serial' => DecimalString::pad(DecimalString::increment($serial), (int) $range->serial_width),
            'updated_at' => $this->clock->now(),
        ]);

        $range->save();

        return new AllocatedConsignmentNumberDto((string) $range->range_id, $number);
    }

    public function record(
        string $hqId,
        string $rangeId,
        string $consignmentId,
        string $number,
        string $actorId,
        string $correlationId,
    ): void {
        $allocation = new NumberAllocationRecord;
        $allocation->forceFill([

            'hq_id' => $hqId,
            'range_id' => $rangeId,
            'consignment_id' => $consignmentId,
            'consignment_number' => $number,
            'allocated_by' => $actorId,
            'allocated_at' => $this->clock->now(),
            'correlation_id' => $correlationId,
            'idempotency_key_hash' => null,
        ]);
        $allocation->save();
    }
}
