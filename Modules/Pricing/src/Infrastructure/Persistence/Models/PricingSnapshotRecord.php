<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class PricingSnapshotRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'pricing_snapshots';

    protected $guarded = ['*'];

    public function lines(): HasMany
    {
        return $this->hasMany(PricingChargeLineRecord::class, 'pricing_snapshot_id', 'id')->orderBy('line_number');
    }
}
