<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\TransitionSalesDocumentVersion;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentVersionRepositoryInterface;
use Modules\CrmSales\Domain\Enums\SalesDocumentStatus;
use Modules\CrmSales\Domain\Enums\SalesDocumentTransition;
use Modules\CrmSales\Domain\Support\SalesDocumentContentHash;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentRecord;
use Modules\CrmSales\Infrastructure\Persistence\Models\SalesDocumentVersionRecord;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Issues, accepts or cancels the revision a sales document currently shows. Only that revision moves: an
 * older one has already been superseded and the history of what it said is not rewritten.
 *
 * Issuing is the moment the content freezes. The customer is copied into the revision and the content is
 * fingerprinted there and then, so an issued document stays readable and provable even after the live
 * customer record moves on.
 */
final readonly class TransitionSalesDocumentVersionHandler
{
    /** Which statuses each move may be made from. */
    private const ALLOWED_FROM = [
        SalesDocumentTransition::ISSUE->value => [SalesDocumentStatus::DRAFT],
        SalesDocumentTransition::ACCEPT->value => [SalesDocumentStatus::ISSUED],
        SalesDocumentTransition::CANCEL->value => [SalesDocumentStatus::DRAFT, SalesDocumentStatus::ISSUED],
    ];

    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private SalesDocumentAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private SalesDocumentRepositoryInterface $salesDocuments,
        private SalesDocumentVersionRepositoryInterface $versions,
    ) {}

    public function handle(TransitionSalesDocumentVersionCommand $command): TransitionSalesDocumentVersionResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);

        return $this->connection->transaction(function () use ($command, $hqId): TransitionSalesDocumentVersionResult {
            // A revision of another tenant is indistinguishable from one that does not exist. The row is
            // locked for the whole transaction, so the status check below cannot be raced past.
            $version = $this->versions->lockForTenant($hqId, $command->versionId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            $document = $this->salesDocuments->findForTenant($hqId, (string) $version->document_id)
                ?? throw new ApiException(ApiErrorCode::InternalServerError, 500, 'common.unexpected_error_occurred');

            if ((string) $document->current_version_id !== $command->versionId) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'sales.superseded_revision_cannot_move',
                    details: ['current_version_id' => (string) $document->current_version_id]);
            }
            if (! in_array($version->status, self::ALLOWED_FROM[$command->transition->value], true)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'sales.transition_does_not_fit_status',
                    details: ['status' => $version->status->value]);
            }

            $at = $this->clock->now();
            $this->versions->update($hqId, $command->versionId, match ($command->transition) {
                SalesDocumentTransition::ISSUE => $this->issued($hqId, $document, $version, $at),
                SalesDocumentTransition::ACCEPT => $this->accepted($version, $at),
                SalesDocumentTransition::CANCEL => ['status' => SalesDocumentStatus::CANCELLED->value],
            });

            return new TransitionSalesDocumentVersionResult(
                $this->salesDocuments->findForTenant($hqId, (string) $version->document_id) ?? $document,
                $this->versions->findForTenant($hqId, $command->versionId) ?? $version,
            );
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function issued(string $hqId, SalesDocumentRecord $document, SalesDocumentVersionRecord $version, DateTimeImmutable $at): array
    {
        $this->assertStillValid($version, $at, 'sales.expired_document_cannot_be_issued');
        $customer = $this->customerRepository->findForTenant($hqId, (string) $document->customer_id)
            ?? throw new ApiException(ApiErrorCode::InternalServerError, 500, 'common.unexpected_error_occurred');
        $snapshot = [
            'customer_id' => $customer->customer_id,
            'display_name' => $customer->display_name,
            'customer_code' => $customer->customer_code,
        ];
        $expiresAt = $version->expires_at->format(DATE_ATOM);

        return [
            'status' => SalesDocumentStatus::ISSUED->value,
            'issued_at' => $at,
            'customer_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'content_hash' => SalesDocumentContentHash::of(
                (string) $document->document_no,
                $document->document_type->value,
                (string) $version->currency,
                (int) $version->total,
                $version->terms,
                $expiresAt,
                $snapshot,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function accepted(SalesDocumentVersionRecord $version, DateTimeImmutable $at): array
    {
        $this->assertStillValid($version, $at, 'sales.expired_document_cannot_be_accepted');

        return ['status' => SalesDocumentStatus::ACCEPTED->value];
    }

    /** Neither issuing nor accepting makes sense once the validity the document itself states has run out. */
    private function assertStillValid(SalesDocumentVersionRecord $version, DateTimeImmutable $at, string $messageKey): void
    {
        if ($version->expires_at === null || $version->expires_at <= $at) {
            throw new ApiException(ApiErrorCode::Conflict, 409, $messageKey);
        }
    }
}
