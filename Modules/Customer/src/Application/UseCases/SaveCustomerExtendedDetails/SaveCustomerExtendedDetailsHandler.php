<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerExtendedDetailRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class SaveCustomerExtendedDetailsHandler
{
    private const COLUMNS = [
        'salutation', 'birth_date', 'trade_name', 'legal_form', 'legal_name', 'registration_no',
        'registration_date', 'registration_place', 'need_summary', 'budget', 'budget_known', 'authority_note',
        'need_confirmed', 'timeframe', 'qualification_result', 'evaluated_by', 'evaluated_at',
    ];

    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerExtendedDetailRepositoryInterface $customerExtendedDetailRepository,
    ) {}

    public function handle(SaveCustomerExtendedDetailsCommand $command): SaveCustomerExtendedDetailsResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        $details = $this->connection->transaction(function () use ($command, $hqId, $input): CustomerExtendedDetailRecord {
            // A customer of another tenant is indistinguishable from one that does not exist.
            if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            $at = $this->clock->now();
            $this->customerExtendedDetailRepository->save([
                'hq_id' => $hqId,
                'customer_id' => $command->customerId,
                'salutation' => $input->salutation,
                // A calendar day, so only the date part of the submitted instant is kept.
                'birth_date' => $input->birthDate?->format('Y-m-d'),
                'trade_name' => $input->tradeName,
                'legal_form' => $input->legalForm,
                'legal_name' => $input->legalName,
                'registration_no' => $input->registrationNo,
                'registration_date' => $input->registrationDate?->format('Y-m-d'),
                'registration_place' => $input->registrationPlace,
                'need_summary' => $input->needSummary,
                'budget' => $input->budget,
                'budget_known' => $input->budgetKnown,
                'authority_note' => $input->authorityNote,
                'need_confirmed' => $input->needConfirmed,
                'timeframe' => $input->timeframe,
                'qualification_result' => $input->qualificationResult,
                'evaluated_by' => $command->actor->userId,
                'evaluated_at' => $at,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ], [...self::COLUMNS, 'updated_at']);

            return $this->customerExtendedDetailRepository->findForCustomer($hqId, $command->customerId)
                ?? throw new ApiException(ApiErrorCode::InternalServerError, 500, 'common.unexpected_error_occurred');
        }, attempts: 3);

        return new SaveCustomerExtendedDetailsResult($details);
    }
}
