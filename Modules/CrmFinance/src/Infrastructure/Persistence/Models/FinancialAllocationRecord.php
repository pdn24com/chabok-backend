<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/** How much of one receipt was set against one external invoice. */
final class FinancialAllocationRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_financial_allocations';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            // Rial amounts are whole numbers; the column is a big integer and stays one in the payload.
            'amount' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
