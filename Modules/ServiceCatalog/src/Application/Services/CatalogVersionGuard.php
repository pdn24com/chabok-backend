<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CatalogVersionGuard
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler $validateCatalogDraft,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
    )
    {
    }

    public function approvalChanges(AuthenticatedPrincipal $actor, object $row, string $resource, string $versionId): array
    {
        if (!in_array((string) $row->status, ['DRAFT', 'READY_FOR_APPROVAL'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a validated draft can be approved.');
        }
        $validation = $this->validateCatalogDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand($actor, $resource, $versionId))->data;
        if (!$validation['valid']) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Catalog validation failed.', details: $validation);
        }
        return ['status' => 'APPROVED', 'approved_by' => $actor->userId, 'approved_at' => $this->clock->now()];
    }

    public function publicationChanges(AuthenticatedPrincipal $actor, object $row, string $resource, string $versionId): array
    {
        if ((string) $row->status !== 'APPROVED') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.');
        }
        $detail = $this->catalogReader->versionDetail($actor, $resource, $versionId);
        return [
            'status' => 'PUBLISHED',
            'published_by' => $actor->userId,
            'published_at' => $this->clock->now(),
            'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public function simpleTransition(object $row, string $from, string $to): array
    {
        if ((string) $row->status !== $from) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, "Only {$from} can transition to {$to}.");
        }
        return ['status' => $to];
    }
}
