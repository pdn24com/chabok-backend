<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ArchiveDocument;

use Illuminate\Database\ConnectionInterface;
use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Retires a document. Nothing is deleted and the links survive, so a record that once carried the
 * document still says so; only the archive stops offering it as current.
 */
final readonly class ArchiveDocumentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private DocumentAccessGuardInterface $accessGuard,
        private DocumentRepositoryInterface $documentRepository,
    ) {}

    public function handle(ArchiveDocumentCommand $command): ArchiveDocumentResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);

        return $this->connection->transaction(function () use ($command, $hqId): ArchiveDocumentResult {
            // A document of another tenant is indistinguishable from one that does not exist.
            $current = $this->documentRepository->lockForTenant($hqId, $command->documentId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            if ($current->status === DocumentStatus::ARCHIVED) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['*' => ['document.document_is_already_archived']]);
            }

            $this->documentRepository->update($hqId, $command->documentId, ['status' => DocumentStatus::ARCHIVED->value]);
            $stored = $this->documentRepository->findForTenant($hqId, $command->documentId) ?? $current;

            return new ArchiveDocumentResult($stored);
        }, attempts: 3);
    }
}
