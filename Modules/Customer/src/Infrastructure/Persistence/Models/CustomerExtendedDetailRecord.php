<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/** At most one row per customer, carrying the registration identity and the qualification notes. */
final class CustomerExtendedDetailRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_customer_extended_details';

    protected $guarded = ['*'];

    /** The evaluator the server stamped on the qualification, named where the form prints it. */
    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(UserRecord::class, 'evaluated_by');
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'immutable_date',
            // Rial amounts are whole numbers; the column is a big integer and stays one in the payload.
            'budget' => 'integer',
            'registration_date' => 'immutable_date',
            'budget_known' => 'boolean',
            'need_confirmed' => 'boolean',
            'evaluated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
