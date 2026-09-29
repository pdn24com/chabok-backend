<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Customer\Domain\Enums\CreditRating;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** At most one row per customer, holding the manually maintained financial summary of the account. */
final class CustomerFinancialDetailRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_customer_financial_details';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'credit_rating' => CreditRating::class,
            'financial_reference_date' => 'immutable_date',
            // Rial amounts are whole numbers; the column is a big integer and stays one in the payload.
            'credit_limit' => 'integer',
            'revenue' => 'integer',
            'receipts' => 'integer',
            'direct_cost' => 'integer',
            'balance' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
