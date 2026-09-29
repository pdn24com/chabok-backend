<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/**
 * A reference to an invoice issued outside the CRM. Recording one recognises no revenue by itself; it
 * exists so a receipt can be pointed at the document it settles.
 */
final class ExternalInvoiceRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_external_invoices';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            // Rial amounts are whole numbers; the column is a big integer and stays one in the payload.
            'amount' => 'integer',
            'issued_on' => 'immutable_date',
            'due_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
