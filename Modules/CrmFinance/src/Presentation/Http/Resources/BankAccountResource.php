<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmFinance\Domain\Support\AccountNumberMask;
use Modules\CrmFinance\Infrastructure\Persistence\Models\BankAccountRecord;

/**
 * One bank account of a customer. The three numbers leave the system masked and never whole, so this
 * payload identifies an account without being enough to pay out of it.
 *
 * @mixin BankAccountRecord
 */
final class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'bank_account_id' => $this->bank_account_id,
            'customer_id' => $this->customer_id,
            'bank_name' => $this->bank_name,
            'iban_masked' => AccountNumberMask::iban($this->iban),
            'card_number_masked' => AccountNumberMask::cardNumber($this->card_number),
            'account_no_masked' => AccountNumberMask::accountNo($this->account_no),
            'is_primary' => $this->is_primary,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
