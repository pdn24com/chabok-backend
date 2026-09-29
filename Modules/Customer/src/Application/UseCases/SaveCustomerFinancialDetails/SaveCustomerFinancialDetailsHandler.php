<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerFinancialDetails;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerFinancialDetailRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerFinancialDetailRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * The summary is typed in by hand and is never reconciled against the individual financial entries: the
 * two are separate readings of the same account, and adding the entries up would not reproduce this row.
 */
final readonly class SaveCustomerFinancialDetailsHandler
{
    /** A PUT replaces the whole summary, so every column of the form is rewritten on each save. */
    private const COLUMNS = [
        'credit_limit', 'credit_rating', 'settlement_terms', 'financial_reference_date', 'source_note',
        'accounting_code', 'accounting_title', 'revenue', 'receipts', 'direct_cost', 'balance',
    ];

    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerFinancialDetailRepositoryInterface $customerFinancialDetailRepository,
    ) {}

    public function handle(SaveCustomerFinancialDetailsCommand $command): SaveCustomerFinancialDetailsResult
    {
        $hqId = $this->accessGuard->assertCanEditFinance($command->actor);
        $input = $command->input;

        $details = $this->connection->transaction(function () use ($command, $hqId, $input): CustomerFinancialDetailRecord {
            // A customer of another tenant is indistinguishable from one that does not exist.
            if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            $at = $this->clock->now();
            $this->customerFinancialDetailRepository->save([
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                'credit_limit' => $input->creditLimit,
                'credit_rating' => $input->creditRating?->value,
                'settlement_terms' => $input->settlementTerms,
                // A calendar day: the date the figures describe, not the moment the row was written.
                'financial_reference_date' => $input->financialReferenceDate->format('Y-m-d'),
                'source_note' => $input->sourceNote,
                'accounting_code' => $input->accountingCode,
                'accounting_title' => $input->accountingTitle,
                'revenue' => $input->revenue,
                'receipts' => $input->receipts,
                'direct_cost' => $input->directCost,
                'balance' => $input->balance,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ], [...self::COLUMNS, 'updated_at']);

            return $this->customerFinancialDetailRepository->findForCustomer($hqId, $command->customerId)
                ?? throw new ApiException(ApiErrorCode::InternalServerError, 500, 'common.unexpected_error_occurred');
        }, attempts: 3);

        return new SaveCustomerFinancialDetailsResult($details);
    }
}
