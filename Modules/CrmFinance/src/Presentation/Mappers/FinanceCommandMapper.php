<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Presentation\Mappers;

use DateTimeImmutable;
use Modules\CrmFinance\Application\Dto\BankAccountDraftDto;
use Modules\CrmFinance\Application\Dto\ExternalInvoiceDraftDto;
use Modules\CrmFinance\Application\Dto\FinancialAllocationDraftDto;
use Modules\CrmFinance\Application\Dto\FinancialEntryDraftDto;
use Modules\CrmFinance\Application\Dto\FinancialEntryFiltersDto;
use Modules\CrmFinance\Application\UseCases\AllocateReceiptToInvoice\AllocateReceiptToInvoiceCommand;
use Modules\CrmFinance\Application\UseCases\CreateCustomerBankAccount\CreateCustomerBankAccountCommand;
use Modules\CrmFinance\Application\UseCases\CreateCustomerExternalInvoice\CreateCustomerExternalInvoiceCommand;
use Modules\CrmFinance\Application\UseCases\CreateCustomerFinancialEntry\CreateCustomerFinancialEntryCommand;
use Modules\CrmFinance\Application\UseCases\ListCustomerBankAccounts\ListCustomerBankAccountsCommand;
use Modules\CrmFinance\Application\UseCases\ListCustomerExternalInvoices\ListCustomerExternalInvoicesCommand;
use Modules\CrmFinance\Application\UseCases\ListCustomerFinancialEntries\ListCustomerFinancialEntriesCommand;
use Modules\CrmFinance\Domain\Enums\BankAccountStatus;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class FinanceCommandMapper
{
    public static function bankAccountListing(AuthenticatedPrincipal $actor, string $customerId): ListCustomerBankAccountsCommand
    {
        return new ListCustomerBankAccountsCommand($actor, $customerId);
    }

    public static function bankAccountDraft(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCustomerBankAccountCommand
    {
        return new CreateCustomerBankAccountCommand($actor, $customerId, new BankAccountDraftDto(
            bankName: $input['bank_name'],
            status: BankAccountStatus::from($input['status']),
            isPrimary: (bool) ($input['is_primary'] ?? false),
            iban: $input['iban'] ?? null,
            cardNumber: $input['card_number'] ?? null,
            accountNo: $input['account_no'] ?? null,
        ));
    }

    public static function externalInvoiceListing(AuthenticatedPrincipal $actor, string $customerId): ListCustomerExternalInvoicesCommand
    {
        return new ListCustomerExternalInvoicesCommand($actor, $customerId);
    }

    public static function externalInvoiceDraft(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCustomerExternalInvoiceCommand
    {
        return new CreateCustomerExternalInvoiceCommand($actor, $customerId, new ExternalInvoiceDraftDto(
            externalSystem: $input['external_system'],
            referenceNo: $input['reference_no'],
            amount: (int) $input['amount'],
            issuedOn: self::requiredInstant($input['issued_on']),
            dueOn: self::instant($input['due_on'] ?? null),
            opportunityId: isset($input['opportunity_id']) ? (string) $input['opportunity_id'] : null,
        ));
    }

    public static function entryListing(AuthenticatedPrincipal $actor, string $customerId, array $input): ListCustomerFinancialEntriesCommand
    {
        return new ListCustomerFinancialEntriesCommand($actor, $customerId, new FinancialEntryFiltersDto(
            kind: isset($input['kind']) ? FinancialEntryKind::from($input['kind']) : null,
        ));
    }

    public static function entryDraft(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCustomerFinancialEntryCommand
    {
        return new CreateCustomerFinancialEntryCommand($actor, $customerId, new FinancialEntryDraftDto(
            kind: FinancialEntryKind::from($input['kind']),
            amount: (int) $input['amount'],
            effectiveOn: self::requiredInstant($input['effective_on']),
            sourceRef: $input['source_ref'],
            invoiceId: isset($input['invoice_id']) ? (string) $input['invoice_id'] : null,
            reversesId: isset($input['reverses_id']) ? (string) $input['reverses_id'] : null,
        ));
    }

    public static function allocationDraft(AuthenticatedPrincipal $actor, string $entryId, array $input): AllocateReceiptToInvoiceCommand
    {
        return new AllocateReceiptToInvoiceCommand($actor, $entryId, new FinancialAllocationDraftDto(
            invoiceId: (string) $input['invoice_id'],
            amount: (int) $input['amount'],
        ));
    }

    /** A unix timestamp in seconds; the epoch spelling makes the instant UTC whatever the server clock is. */
    private static function instant(int|string|null $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable('@'.$value);
    }

    /** The same instant where the request has already proved the value is there. */
    private static function requiredInstant(int|string $value): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$value);
    }
}
