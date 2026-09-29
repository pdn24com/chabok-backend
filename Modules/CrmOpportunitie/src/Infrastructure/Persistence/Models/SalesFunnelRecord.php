<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** A sales pipeline of the tenant. Its steps are the columns the opportunity board is drawn from. */
final class SalesFunnelRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_sales_funnel';

    protected $guarded = ['*'];

    public function steps(): HasMany
    {
        return $this->hasMany(FunnelStepRecord::class, 'funnel_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
