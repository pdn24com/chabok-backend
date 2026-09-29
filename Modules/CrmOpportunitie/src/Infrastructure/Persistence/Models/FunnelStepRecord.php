<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CrmOpportunitie\Domain\Enums\FunnelStepOutcome;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

final class FunnelStepRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_sales_funnel_steps';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'outcome_type' => FunnelStepOutcome::class,
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
