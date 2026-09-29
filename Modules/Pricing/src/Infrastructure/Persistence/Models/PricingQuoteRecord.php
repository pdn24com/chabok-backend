<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PricingQuoteRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_quotes';

    protected $guarded = ['*'];

    public function snapshots(): HasMany
    {
        return $this->hasMany(PricingSnapshotRecord::class, 'quote_id', 'id');
    }

    public function zoneVersion(): BelongsTo
    {
        return $this->belongsTo(PricingZoneSetVersionRecord::class, 'zone_set_version_id', 'id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PricingQuoteLineRecord::class, 'quote_id', 'id')->orderBy('line_number');
    }

    protected function casts(): array
    {
        return ['normalized_input' => 'array', 'resolution_evidence' => 'array', 'warnings' => 'array'];
    }
}
