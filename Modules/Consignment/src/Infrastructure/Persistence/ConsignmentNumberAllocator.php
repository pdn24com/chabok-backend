<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Consignment\Domain\DecimalString;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ConsignmentNumberAllocator
{
    /** @return array{range_id:string,consignment_number:string} */
    public function next(string $hqId): array
    {
        $range = DB::table('consignment_number_ranges')
            ->where(['hq_id' => $hqId, 'status' => 'AVAILABLE'])
            ->whereNotNull('next_serial')
            ->orderBy('created_at')->orderBy('range_id')
            ->lockForUpdate()->first();
        if ($range === null) {
            throw new ApiException(
                ApiErrorCode::ConsignmentNumberRangeExhausted,
                422,
                'No Consignment number range has remaining capacity.',
            );
        }

        $serial = (string) $range->next_serial;
        $number = (string) $range->numeric_prefix.$serial;
        if (strlen($number) !== (int) $range->total_length || ! preg_match('/^[0-9]+$/D', $number)) {
            throw new ApiException(ApiErrorCode::InternalServerError, 500, 'Unable to allocate a Consignment number.');
        }
        $last = strcmp($serial, (string) $range->serial_end) === 0;
        DB::table('consignment_number_ranges')->where([
            'hq_id' => $hqId, 'range_id' => $range->range_id, 'status' => 'AVAILABLE',
        ])->update($last ? [
            'next_serial' => null, 'status' => 'EXHAUSTED', 'exhausted_at' => now(),
            'updated_at' => now(),
        ] : [
            'next_serial' => DecimalString::pad(DecimalString::increment($serial), (int) $range->serial_width),
            'updated_at' => now(),
        ]);

        return ['range_id' => (string) $range->range_id, 'consignment_number' => $number];
    }

    public function record(string $hqId, string $rangeId, string $consignmentId, string $number, string $actorId, string $correlationId): void
    {
        DB::table('consignment_number_allocations')->insert([
            'allocation_id' => (string) Str::uuid(), 'hq_id' => $hqId, 'range_id' => $rangeId,
            'consignment_id' => $consignmentId, 'consignment_number' => $number,
            'allocated_by' => $actorId, 'allocated_at' => now(), 'correlation_id' => $correlationId,
            'idempotency_key_hash' => null,
        ]);
    }
}
