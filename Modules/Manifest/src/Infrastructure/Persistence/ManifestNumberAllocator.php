<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ManifestNumberAllocator
{
    public function next(): string
    {
        $key = CarbonImmutable::now('UTC')->format('ym');
        DB::table('manifest_number_sequences')->insertOrIgnore([
            'sequence_key' => $key,
            'next_value' => 1,
            'updated_at' => now(),
        ]);
        $row = DB::table('manifest_number_sequences')
            ->where('sequence_key', $key)->lockForUpdate()->first();
        $value = (int) ($row?->next_value ?? 0);
        if ($value < 1 || $value > 99999) {
            throw new ApiException(
                ApiErrorCode::InternalServerError,
                500,
                'Unable to allocate a Manifest number.',
            );
        }
        DB::table('manifest_number_sequences')->where('sequence_key', $key)->update([
            'next_value' => $value + 1,
            'updated_at' => now(),
        ]);

        return sprintf('MNF-%s-%05d', $key, $value);
    }
}
