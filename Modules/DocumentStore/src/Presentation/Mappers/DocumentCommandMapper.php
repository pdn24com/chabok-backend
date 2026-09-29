<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Mappers;

use DateTimeImmutable;
use Modules\DocumentStore\Application\Dto\DocumentCategoryFiltersDto;
use Modules\DocumentStore\Application\Dto\DocumentChangesDto;
use Modules\DocumentStore\Application\Dto\DocumentDraftDto;
use Modules\DocumentStore\Application\Dto\DocumentLinkDto;
use Modules\DocumentStore\Application\Dto\DocumentListFiltersDto;
use Modules\DocumentStore\Application\UseCases\ArchiveDocument\ArchiveDocumentCommand;
use Modules\DocumentStore\Application\UseCases\LinkDocumentToResource\LinkDocumentToResourceCommand;
use Modules\DocumentStore\Application\UseCases\ListDocumentCategories\ListDocumentCategoriesCommand;
use Modules\DocumentStore\Application\UseCases\ListDocuments\ListDocumentsCommand;
use Modules\DocumentStore\Application\UseCases\RegisterDocument\RegisterDocumentCommand;
use Modules\DocumentStore\Application\UseCases\UpdateDocument\UpdateDocumentCommand;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class DocumentCommandMapper
{
    public static function categoryListing(AuthenticatedPrincipal $actor, array $input): ListDocumentCategoriesCommand
    {
        return new ListDocumentCategoriesCommand($actor, new DocumentCategoryFiltersDto(
            // Absent means both the usable and the retired categories, not the same as active=false.
            active: array_key_exists('active', $input) ? (bool) $input['active'] : null,
        ));
    }

    public static function listing(AuthenticatedPrincipal $actor, array $input): ListDocumentsCommand
    {
        return new ListDocumentsCommand($actor, new DocumentListFiltersDto(
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 25),
            status: isset($input['status']) ? DocumentStatus::from($input['status']) : null,
            categoryId: isset($input['category_id']) ? (string) $input['category_id'] : null,
            search: $input['q'] ?? null,
            resourceType: isset($input['resource_type']) ? DocumentResourceType::from($input['resource_type']) : null,
            resourceId: isset($input['resource_id']) ? (string) $input['resource_id'] : null,
        ));
    }

    public static function draft(AuthenticatedPrincipal $actor, array $input): RegisterDocumentCommand
    {
        return new RegisterDocumentCommand($actor, new DocumentDraftDto(
            title: $input['title'],
            classification: $input['classification'],
            categoryId: isset($input['category_id']) ? (string) $input['category_id'] : null,
            referenceNo: $input['reference_no'] ?? null,
            expiresOn: self::instant($input['expires_on'] ?? null),
            links: array_map(self::link(...), array_values($input['links'] ?? [])),
        ));
    }

    public static function changes(AuthenticatedPrincipal $actor, string $documentId, array $input): UpdateDocumentCommand
    {
        return new UpdateDocumentCommand($actor, $documentId, new DocumentChangesDto(
            title: $input['title'] ?? null,
            classification: $input['classification'] ?? null,
            // array_key_exists, not isset: an explicit null clears the field and must reach the handler.
            categoryId: isset($input['category_id']) ? (string) $input['category_id'] : null,
            categorySpecified: array_key_exists('category_id', $input),
            referenceNo: $input['reference_no'] ?? null,
            referenceNoSpecified: array_key_exists('reference_no', $input),
            expiresOn: self::instant($input['expires_on'] ?? null),
            expiresOnSpecified: array_key_exists('expires_on', $input),
        ));
    }

    public static function archival(AuthenticatedPrincipal $actor, string $documentId): ArchiveDocumentCommand
    {
        return new ArchiveDocumentCommand($actor, $documentId);
    }

    public static function attachment(AuthenticatedPrincipal $actor, string $documentId, array $input): LinkDocumentToResourceCommand
    {
        return new LinkDocumentToResourceCommand($actor, $documentId, self::link($input));
    }

    /** @param array<string, mixed> $link */
    private static function link(array $link): DocumentLinkDto
    {
        return new DocumentLinkDto(
            resourceType: DocumentResourceType::from($link['resource_type']),
            resourceId: (string) $link['resource_id'],
            purpose: $link['purpose'] ?? null,
        );
    }

    /** A unix timestamp in seconds; the epoch spelling makes the instant UTC whatever the server clock is. */
    private static function instant(int|string|null $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable('@'.$value);
    }
}
