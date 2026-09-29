<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CrmOpportunitie\Domain\Enums\FunnelStepOutcome;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/**
 * One move of an opportunity through the funnels. The table is append-only and carries the codes and
 * titles of both ends as they read at the moment of the move, so renaming a step later never rewrites
 * the history of what an operator actually did.
 */
final class OpportunityEventRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_opportunity_events';

    protected $guarded = ['*'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'actor_id');
    }

    protected function casts(): array
    {
        return [
            'from_outcome_type' => FunnelStepOutcome::class,
            'to_outcome_type' => FunnelStepOutcome::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
