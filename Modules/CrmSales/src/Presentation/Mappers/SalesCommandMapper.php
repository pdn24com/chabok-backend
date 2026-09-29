<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Mappers;

use DateTimeImmutable;
use DateTimeZone;
use Modules\CrmSales\Application\Dto\SalesDocumentDraftDto;
use Modules\CrmSales\Application\Dto\SalesDocumentFiltersDto;
use Modules\CrmSales\Application\Dto\SalesDocumentRevisionDto;
use Modules\CrmSales\Application\UseCases\CreateSalesDocument\CreateSalesDocumentCommand;
use Modules\CrmSales\Application\UseCases\CreateSalesDocumentVersion\CreateSalesDocumentVersionCommand;
use Modules\CrmSales\Application\UseCases\GetSalesDocument\GetSalesDocumentCommand;
use Modules\CrmSales\Application\UseCases\ListSalesDocuments\ListSalesDocumentsCommand;
use Modules\CrmSales\Application\UseCases\TransitionSalesDocumentVersion\TransitionSalesDocumentVersionCommand;
use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;
use Modules\CrmSales\Domain\Enums\SalesDocumentTransition;
use Modules\CrmSales\Domain\Enums\SalesDocumentType;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class SalesCommandMapper
{
    public static function listing(AuthenticatedPrincipal $actor, array $input): ListSalesDocumentsCommand
    {
        return new ListSalesDocumentsCommand($actor, new SalesDocumentFiltersDto(
            customerId: isset($input['customer_id']) ? (string) $input['customer_id'] : null,
            status: isset($input['status']) ? SalesDocumentStatus::from($input['status']) : null,
        ));
    }

    public static function document(AuthenticatedPrincipal $actor, string $documentId): GetSalesDocumentCommand
    {
        return new GetSalesDocumentCommand($actor, $documentId);
    }

    public static function draft(AuthenticatedPrincipal $actor, array $input): CreateSalesDocumentCommand
    {
        $version = $input['version'];
        $documentNo = isset($input['document_no']) ? trim((string) $input['document_no']) : '';

        return new CreateSalesDocumentCommand($actor, new SalesDocumentDraftDto(
            opportunityId: (string) $input['opportunity_id'],
            documentType: SalesDocumentType::from($input['document_type']),
            currency: $version['currency'],
            total: (int) $version['total'],
            expiresAt: self::instant($version['expires_at']),
            // An empty number is the same request as no number at all: both ask the server to name it.
            documentNo: $documentNo === '' ? null : $documentNo,
            terms: $version['terms'] ?? null,
        ));
    }

    public static function revision(AuthenticatedPrincipal $actor, string $documentId, array $input): CreateSalesDocumentVersionCommand
    {
        return new CreateSalesDocumentVersionCommand($actor, $documentId, new SalesDocumentRevisionDto(
            currency: $input['currency'] ?? null,
            total: isset($input['total']) ? (int) $input['total'] : null,
            expiresAt: isset($input['expires_at']) ? self::instant($input['expires_at']) : null,
            // array_key_exists, not isset: an explicit null clears the terms and must reach the handler.
            terms: $input['terms'] ?? null,
            termsSpecified: array_key_exists('terms', $input),
        ));
    }

    public static function transition(AuthenticatedPrincipal $actor, string $versionId, SalesDocumentTransition $transition): TransitionSalesDocumentVersionCommand
    {
        return new TransitionSalesDocumentVersionCommand($actor, $versionId, $transition);
    }

    private static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
