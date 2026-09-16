<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Domain\DecimalString;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ConsignmentNumberAllocator
{
    public function __construct(
        private \Modules\Consignment\Application\Repositories\NumberRangeRepository $ranges,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }
    /** @return array{range_id:string,consignment_number:string} */

    public function next(string $hqId): array
    {
        $range = $this->ranges->lockNextAvailable($hqId);
        if ($range === null) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberRangeExhausted, 422, 'No Consignment number range has remaining capacity.');
        }
        $serial = (string) $range->next_serial;
        $number = (string) $range->numeric_prefix . $serial;
        if (strlen($number) !== (int) $range->total_length || !preg_match('/^[0-9]+$/D', $number)) {
            throw new ApiException(ApiErrorCode::InternalServerError, 500, 'Unable to allocate a Consignment number.');
        }
        $last = strcmp($serial, (string) $range->serial_end) === 0;
        $this->ranges->advanceAvailable($hqId, $range->range_id, $last ? [
            'next_serial' => null,
            'status' => 'EXHAUSTED',
            'exhausted_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ] : [
            'next_serial' => DecimalString::pad(DecimalString::increment($serial), (int) $range->serial_width),
            'updated_at' => $this->clock->now(),
        ]);
        return ['range_id' => (string) $range->range_id, 'consignment_number' => $number];
    }

    public function record(
        string $hqId,
        string $rangeId,
        string $consignmentId,
        string $number,
        string $actorId,
        string $correlationId,
    ): void
    {
        $this->ranges->appendAllocation([
            'allocation_id' => $this->identifiers->uuid(),
            'hq_id' => $hqId,
            'range_id' => $rangeId,
            'consignment_id' => $consignmentId,
            'consignment_number' => $number,
            'allocated_by' => $actorId,
            'allocated_at' => $this->clock->now(),
            'correlation_id' => $correlationId,
            'idempotency_key_hash' => null,
        ]);
    }
}
