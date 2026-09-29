<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\CrmFinance\Domain\Enums\BankAccountStatus;
use Modules\Foundation\Infrastructure\Persistence\HasNumericIdentity;

/**
 * One bank account of a customer. The IBAN, card and account numbers are stored whole and never leave
 * the system unmasked, so nothing here is safe to hand to a client as it stands.
 */
final class BankAccountRecord extends Model
{
    use HasNumericIdentity;

    public $timestamps = false;

    protected $table = 'crm_bank_accounts';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'status' => BankAccountStatus::class,
            'is_primary' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
