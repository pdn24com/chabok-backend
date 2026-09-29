<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerBankAccount;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\BankAccountRepositoryInterface;
use Modules\CrmFinance\Domain\Enums\BankAccountStatus;
use Modules\CrmFinance\Infrastructure\Persistence\Models\BankAccountRecord;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CreateCustomerBankAccountHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private FinanceAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private BankAccountRepositoryInterface $bankAccountRepository,
    ) {}

    public function handle(CreateCustomerBankAccountCommand $command): CreateCustomerBankAccountResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $bankAccount = $this->connection->transaction(function () use ($command, $hqId, $input): BankAccountRecord {
            // A customer of another tenant is indistinguishable from one that does not exist.
            if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($input->namesNoNumber()) {
                throw $this->invalid('iban', 'finance.bank_account_needs_one_number');
            }
            if ($input->isPrimary && $input->status !== BankAccountStatus::ACTIVE) {
                throw $this->invalid('is_primary', 'finance.primary_bank_account_must_be_active');
            }

            // The first account of a customer becomes the primary one whatever the form said, so there is
            // always one the rest of the system can fall back to. Only one active account may hold the
            // flag and the database enforces it, so the old holder is cleared before the new one claims it.
            $isPrimary = $input->status === BankAccountStatus::ACTIVE
                && ($input->isPrimary || ! $this->bankAccountRepository->existsForCustomer($hqId, $command->customerId));
            if ($isPrimary) {
                $this->bankAccountRepository->clearPrimary($hqId, $command->customerId);
            }

            return $this->bankAccountRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                'bank_name' => $input->bankName,
                'iban' => $input->iban,
                'card_number' => $input->cardNumber,
                'account_no' => $input->accountNo,
                'is_primary' => $isPrimary,
                'status' => $input->status->value,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]);
        }, attempts: 3);

        return new CreateCustomerBankAccountResult($bankAccount);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
