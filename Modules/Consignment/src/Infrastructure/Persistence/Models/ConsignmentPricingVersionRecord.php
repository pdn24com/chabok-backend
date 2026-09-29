<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class ConsignmentPricingVersionRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'consignment_pricing_versions';

    protected $guarded = ['*'];

    public function chargeLines(): HasMany
    {
        return $this->hasMany(ConsignmentPricingChargeLineRecord::class, 'pricing_version_id', 'id')->orderBy('line_number');
    }

    protected function casts(): array
    {
        return ['delivery_windows' => 'array', 'version_number' => 'integer', 'quote_version' => 'integer'];
    }
}
