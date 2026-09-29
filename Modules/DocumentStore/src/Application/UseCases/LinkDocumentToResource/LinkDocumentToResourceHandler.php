<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\LinkDocumentToResource;

use Illuminate\Database\ConnectionInterface;
use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Ports\DocumentResourceDirectoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentLinkRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Attaches an existing document to one more record. The same document reaches several records this way,
 * which is the point of the archive: one contract text is filed once and read from wherever it applies.
 */
final readonly class LinkDocumentToResourceHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private DocumentAccessGuardInterface $accessGuard,
        private DocumentResourceDirectoryInterface $resourceDirectory,
        private DocumentRepositoryInterface $documentRepository,
        private DocumentLinkRepositoryInterface $documentLinkRepository,
    ) {}

    public function handle(LinkDocumentToResourceCommand $command): LinkDocumentToResourceResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        return $this->connection->transaction(function () use ($command, $hqId, $input): LinkDocumentToResourceResult {
            // A document of another tenant is indistinguishable from one that does not exist.
            $document = $this->documentRepository->lockForTenant($hqId, $command->documentId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            // A retired document is not attached to anything new; the links it already has stay.
            if ($document->status === DocumentStatus::ARCHIVED) {
                throw $this->invalid('*', 'document.archived_document_cannot_be_attached');
            }
            if (! $this->resourceDirectory->exists($hqId, $input->resourceType, $input->resourceId)) {
                throw $this->invalid('resource_id', 'document.select_existing_resource_of_the_tenant');
            }
            // The database keeps one link per pair, so a repeat is reported rather than left to fail.
            if ($this->documentLinkRepository->exists($hqId, $command->documentId, $input->resourceType, $input->resourceId)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'common.requested_change_conflicts_with_existing_data');
            }

            return new LinkDocumentToResourceResult($this->documentLinkRepository->create([
                'hq_id' => $hqId,
                'document_id' => $command->documentId,
                'resource_type' => $input->resourceType->value,
                'resource_id' => $input->resourceId,
                'purpose' => $input->purpose,
                'created_by' => $command->actor->userId,
                'created_at' => $this->clock->now(),
            ]));
        }, attempts: 3);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
