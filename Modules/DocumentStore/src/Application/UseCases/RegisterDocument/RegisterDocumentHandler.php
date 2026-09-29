<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\RegisterDocument;

use Illuminate\Database\ConnectionInterface;
use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Dto\DocumentLinkDto;
use Modules\DocumentStore\Application\Ports\DocumentResourceDirectoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentCategoryRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentLinkRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Registers a document together with the records it is attached to, in one write. The bytes of the file
 * are a separate decision: this records what the document is and what it belongs to, nothing more.
 */
final readonly class RegisterDocumentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private DocumentAccessGuardInterface $accessGuard,
        private DocumentResourceDirectoryInterface $resourceDirectory,
        private DocumentCategoryRepositoryInterface $documentCategoryRepository,
        private DocumentRepositoryInterface $documentRepository,
        private DocumentLinkRepositoryInterface $documentLinkRepository,
    ) {}

    public function handle(RegisterDocumentCommand $command): RegisterDocumentResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        return $this->connection->transaction(function () use ($command, $hqId, $input): RegisterDocumentResult {
            if ($input->categoryId !== null
                && ! $this->documentCategoryRepository->activeExistsForTenant($hqId, $input->categoryId)) {
                throw $this->invalid('category_id', 'document.select_active_category_from_reference_data');
            }
            // Every link is proven before anything is written, so a document is never filed half attached.
            $this->assertLinksResolve($hqId, $input->links);

            $at = $this->clock->now();
            $document = $this->documentRepository->create([
                'hq_id' => $hqId,
                'title' => $input->title,
                'category_id' => $input->categoryId,
                'classification' => $input->classification,
                'reference_no' => $input->referenceNo,
                // A calendar day, so only the date part of the submitted instant is kept.
                'expires_on' => $input->expiresOn?->format('Y-m-d'),
                'status' => DocumentStatus::ACTIVE->value,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            foreach ($input->links as $link) {
                $this->documentLinkRepository->create([
                    'hq_id' => $hqId,
                    'document_id' => $document->document_id,
                    'resource_type' => $link->resourceType->value,
                    'resource_id' => $link->resourceId,
                    'purpose' => $link->purpose,
                    'created_by' => $command->actor->userId,
                    'created_at' => $at,
                ]);
            }

            // Read back, so the response names the category and the links instead of their IDs alone.
            $stored = $this->documentRepository->findForTenant($hqId, $document->document_id) ?? $document;

            return new RegisterDocumentResult($stored);
        }, attempts: 3);
    }

    /**
     * Every record a document claims to belong to has to exist, and the same record is never named
     * twice: the database keeps one link per pair and a repeated one is a mistake in the form.
     *
     * @param  list<DocumentLinkDto>  $links
     */
    private function assertLinksResolve(string $hqId, array $links): void
    {
        $seen = [];
        foreach ($links as $index => $link) {
            $pair = $link->resourceType->value.':'.$link->resourceId;
            if (isset($seen[$pair])) {
                throw $this->invalid("links.{$index}.resource_id", 'document.resource_is_named_twice');
            }
            $seen[$pair] = true;
            if (! $this->resourceDirectory->exists($hqId, $link->resourceType, $link->resourceId)) {
                throw $this->invalid("links.{$index}.resource_id", 'document.select_existing_resource_of_the_tenant');
            }
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
