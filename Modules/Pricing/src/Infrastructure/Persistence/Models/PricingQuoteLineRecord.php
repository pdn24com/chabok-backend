<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PricingQuoteLineRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_quote_lines';

    protected $guarded = ['*'];

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(PricingChargeTypeRecord::class, 'charge_type_id', 'id');
    }

    protected function casts(): array
    {
        return ['explanation' => 'array'];
    }
}
