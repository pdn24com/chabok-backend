<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Consignment\Domain\Support\DecimalString;
use Modules\Consignment\Infrastructure\Persistence\Models\NumberRangeRecord;

final class NumberRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var NumberRangeRecord $row */
        $row = $this->resource;
        $capacity = DecimalString::inclusiveCount((string) $row->serial_start, (string) $row->serial_end);
        $allocated = $row->next_serial === null ? $capacity : DecimalString::subtract((string) $row->next_serial, (string) $row->serial_start);

        return [
            'range_id' => (string) $row->range_id,
            'title' => (string) $row->title,
            'numeric_prefix' => (string) $row->numeric_prefix,
            'total_length' => (int) $row->total_length,
            'serial_width' => (int) $row->serial_width,
            'serial_start' => (string) $row->serial_start,
            'serial_end' => (string) $row->serial_end,
            'first_number' => (string) $row->first_number,
            'last_number' => (string) $row->last_number,
            'next_number' => $row->next_serial === null ? null : (string) $row->numeric_prefix.$row->next_serial,
            'total_capacity' => $capacity,
            'allocated_count' => $allocated,
            'remaining_count' => DecimalString::subtract($capacity, $allocated),
            'status' => $row->status->value,
            'created_by' => (string) $row->created_by,
            'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
            'disabled_by' => $row->disabled_by === null ? null : (string) $row->disabled_by,
            'disabled_at' => $row->disabled_at === null ? null : (string) $row->disabled_at,
            'exhausted_at' => $row->exhausted_at === null ? null : (string) $row->exhausted_at,
        ];
    }
}
