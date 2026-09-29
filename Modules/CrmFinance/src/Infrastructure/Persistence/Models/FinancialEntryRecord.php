<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/**
 * One manually recorded financial line of a customer. Entries are never edited or deleted: a correction
 * is a new entry that names the one it reverses.
 */
final class FinancialEntryRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_financial_entries';

    protected $guarded = ['*'];

    /** What has already been set against invoices out of this entry; only a receipt ever carries any. */
    public function allocations(): HasMany
    {
        return $this->hasMany(FinancialAllocationRecord::class, 'receipt_entry_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => FinancialEntryKind::class,
            // Rial amounts are whole numbers; the column is a big integer and stays one in the payload.
            'amount' => 'integer',
            'effective_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
