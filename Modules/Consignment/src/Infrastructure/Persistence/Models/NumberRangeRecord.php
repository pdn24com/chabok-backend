<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Consignment\Domain\Enums\NumberRangeStatus;
use Modules\Consignment\Domain\ValueObjects\NumberRangePreview;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class NumberRangeRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'consignment_number_ranges';

    protected $guarded = ['*'];

    public function scopeOverlapping(Builder $query, NumberRangePreview $preview): Builder
    {
        // Number intervals are globally reserved, including disabled and exhausted ranges.
        return $query->where('total_length', $preview->totalLength)
            ->where('first_number', '<=', $preview->lastNumber)
            ->where('last_number', '>=', $preview->firstNumber);
    }

    protected function casts(): array
    {
        return ['status' => NumberRangeStatus::class, 'total_length' => 'integer', 'serial_width' => 'integer'];
    }
}
