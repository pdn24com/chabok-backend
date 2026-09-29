<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Mappers;

use DateTimeImmutable;
use DateTimeZone;
use Modules\CrmSales\Application\Dto\ContractDraftDto;
use Modules\CrmSales\Application\UseCases\CreateCustomerContract\CreateCustomerContractCommand;
use Modules\CrmSales\Application\UseCases\ListCustomerContracts\ListCustomerContractsCommand;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class ContractCommandMapper
{
    public static function listing(AuthenticatedPrincipal $actor, string $customerId): ListCustomerContractsCommand
    {
        return new ListCustomerContractsCommand($actor, $customerId);
    }

    public static function draft(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCustomerContractCommand
    {
        $status = isset($input['status']) ? trim((string) $input['status']) : '';

        return new CreateCustomerContractCommand($actor, $customerId, new ContractDraftDto(
            referenceNo: $input['reference_no'],
            // An empty status is the same request as none at all: the contract opens as a draft.
            status: $status === '' ? 'DRAFT' : $status,
            opportunityId: isset($input['opportunity_id']) ? (string) $input['opportunity_id'] : null,
            proformaVersionId: isset($input['proforma_version_id']) ? (string) $input['proforma_version_id'] : null,
            startDate: self::day($input['start_date'] ?? null),
            endDate: self::day($input['end_date'] ?? null),
            amount: isset($input['amount']) ? (int) $input['amount'] : null,
            commitments: $input['commitments'] ?? null,
        ));
    }

    private static function day(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value.' 00:00:00', new DateTimeZone('UTC'));
    }
}
