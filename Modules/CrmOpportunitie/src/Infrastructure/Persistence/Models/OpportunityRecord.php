<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class OpportunityRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_opportunities';

    protected $guarded = ['*'];

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(FunnelStepRecord::class, 'current_step_id');
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(SalesFunnelRecord::class, 'funnel_id');
    }

    /** A LEAD as readily as a CUSTOMER: an opportunity is opened before the record is promoted. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(CustomerRecord::class, 'customer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'assignee_id');
    }

    /**
     * The one unfinished task the card prints, soonest due first. A task without a due date carries no
     * deadline, so it stands behind every dated one rather than in front of them.
     */
    public function nextTask(): HasOne
    {
        return $this->hasOne(TaskRecord::class, 'opportunity_id')
            ->whereNotIn('status', TaskStatus::closedValues())
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'expected_close' => 'immutable_date',
            // Rial amounts are whole numbers; the column is a big integer and stays one in the payload.
            'amount' => 'integer',
            // The stored two-place precision is the contract, and a float would round the percentage
            // the operator typed. The cast also keeps the spelling identical across database drivers.
            'probability' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }
}
