<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Dto;

use Modules\CrmFinance\Domain\Enums\BankAccountStatus;

/** A new bank account of a customer, as the form sends it. At least one of the three numbers is present. */
final readonly class BankAccountDraftDto
{
    public function __construct(
        public string $bankName,
        public BankAccountStatus $status,
        public bool $isPrimary = false,
        public ?string $iban = null,
        public ?string $cardNumber = null,
        public ?string $accountNo = null,
    ) {}

    /** True while the account names no number at all, which leaves nothing to pay into. */
    public function namesNoNumber(): bool
    {
        return $this->iban === null && $this->cardNumber === null && $this->accountNo === null;
    }
}
