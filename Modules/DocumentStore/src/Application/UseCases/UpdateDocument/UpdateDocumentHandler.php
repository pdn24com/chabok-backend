<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\UpdateDocument;

use Illuminate\Database\ConnectionInterface;
use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Repositories\DocumentCategoryRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Changes the metadata of a document. Archiving is not among the fields: it is its own action, so a
 * routine edit cannot retire a document by accident.
 */
final readonly class UpdateDocumentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private DocumentAccessGuardInterface $accessGuard,
        private DocumentCategoryRepositoryInterface $documentCategoryRepository,
        private DocumentRepositoryInterface $documentRepository,
    ) {}

    public function handle(UpdateDocumentCommand $command): UpdateDocumentResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['*' => ['document.change_is_empty']]);
        }

        return $this->connection->transaction(function () use ($command, $hqId, $changes): UpdateDocumentResult {
            // A document of another tenant is indistinguishable from one that does not exist.
            $current = $this->documentRepository->lockForTenant($hqId, $command->documentId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            // An archived document is a closed record; reopening it is a decision, not an edit.
            if ($current->status === DocumentStatus::ARCHIVED) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['*' => ['document.archived_document_cannot_be_changed']]);
            }
            if ($changes->categorySpecified && $changes->categoryId !== null
                && ! $this->documentCategoryRepository->activeExistsForTenant($hqId, $changes->categoryId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['category_id' => ['document.select_active_category_from_reference_data']]);
            }

            $attributes = [];
            foreach (['title' => $changes->title, 'classification' => $changes->classification] as $column => $value) {
                if ($value !== null) {
                    $attributes[$column] = $value;
                }
            }
            if ($changes->categorySpecified) {
                $attributes['category_id'] = $changes->categoryId;
            }
            if ($changes->referenceNoSpecified) {
                $attributes['reference_no'] = $changes->referenceNo;
            }
            if ($changes->expiresOnSpecified) {
                // A calendar day, so only the date part of the submitted instant is kept.
                $attributes['expires_on'] = $changes->expiresOn?->format('Y-m-d');
            }
            $this->documentRepository->update($hqId, $command->documentId, $attributes);

            $stored = $this->documentRepository->findForTenant($hqId, $command->documentId) ?? $current;

            return new UpdateDocumentResult($stored);
        }, attempts: 3);
    }
}
