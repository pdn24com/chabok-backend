<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ConsignmentNumberAllocator
{
    public function next(): string
    {
        $key = CarbonImmutable::now('UTC')->format('ym');
        DB::table('consignment_number_sequences')->insertOrIgnore([
            'sequence_key' => $key,
            'next_value' => 1,
            'updated_at' => now(),
        ]);
        $row = DB::table('consignment_number_sequences')
            ->where('sequence_key', $key)->lockForUpdate()->first();
        $value = (int) ($row?->next_value ?? 0);
        if ($value < 1 || $value > 999999) {
            throw new ApiException(
                ApiErrorCode::InternalServerError,
                500,
                'Unable to allocate a Consignment number.',
            );
        }
        DB::table('consignment_number_sequences')->where('sequence_key', $key)->update([
            'next_value' => $value + 1,
            'updated_at' => now(),
        ]);

        return sprintf('CHB-%s-%06d', $key, $value);
    }
}
