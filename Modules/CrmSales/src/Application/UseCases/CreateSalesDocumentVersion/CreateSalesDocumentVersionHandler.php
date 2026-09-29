<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateSalesDocumentVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentVersionRepositoryInterface;
use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Supersedes the revision a document currently shows with a fresh draft. This is how an issued document
 * is corrected: the frozen revision stays exactly as it was accepted or refused, and the correction is a
 * new statement that points back at it. Whatever the caller leaves out is carried over unchanged.
 */
final readonly class CreateSalesDocumentVersionHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private SalesDocumentAccessGuardInterface $accessGuard,
        private SalesDocumentRepositoryInterface $salesDocuments,
        private SalesDocumentVersionRepositoryInterface $versions,
    ) {}

    public function handle(CreateSalesDocumentVersionCommand $command): CreateSalesDocumentVersionResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $input = $command->input;

        return $this->connection->transaction(function () use ($command, $hqId, $input): CreateSalesDocumentVersionResult {
            // The row is locked for the whole transaction, so two operators cannot both build revision
            // three out of revision two.
            $document = $this->salesDocuments->lockForTenant($hqId, $command->documentId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            $current = $document->current_version_id === null
                ? null
                : $this->versions->findForTenant($hqId, (string) $document->current_version_id);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::InternalServerError, 500, 'common.unexpected_error_occurred');
            }
            // A draft is still being written, so it is corrected in place rather than superseded.
            if ($current->status === SalesDocumentStatus::DRAFT) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'sales.draft_revision_is_not_superseded');
            }

            $at = $this->clock->now();
            $expiresAt = $input->expiresAt ?? $current->expires_at;
            if ($expiresAt <= $at) {
                throw $this->invalid('expires_at', 'sales.validity_cannot_be_in_the_past');
            }

            $version = $this->versions->create([
                'hq_id' => $hqId,
                'document_id' => $command->documentId,
                'previous_version_id' => $current->sales_document_version_id,
                'version_no' => (int) $current->version_no + 1,
                // The new revision starts blank of everything issuing stamped on the one before it.
                'status' => SalesDocumentStatus::DRAFT->value,
                'expires_at' => $expiresAt,
                'currency' => $input->currency ?? $current->currency,
                'total' => $input->total ?? $current->total,
                'terms' => $input->termsSpecified ? $input->terms : $current->terms,
                'customer_snapshot' => null,
                'content_hash' => null,
                'issued_at' => null,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);
            $this->salesDocuments->update($hqId, $command->documentId, [
                'current_version_id' => $version->sales_document_version_id,
            ]);

            $stored = $this->salesDocuments->findForTenant($hqId, $command->documentId) ?? $document;

            return new CreateSalesDocumentVersionResult($stored, $version);
        }, attempts: 3);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
