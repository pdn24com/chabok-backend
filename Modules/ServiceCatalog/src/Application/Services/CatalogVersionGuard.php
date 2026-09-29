<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogVersionGuardInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;
use Modules\ServiceCatalog\Application\Serialization\CatalogValidationDocument;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class CatalogVersionGuard implements CatalogVersionGuardInterface
{
    public function __construct(
        private ValidateCatalogDraftHandler $validateCatalogDraftHandler,
        private ClockInterface $clock,
        private CatalogReaderInterface $catalogReader,
    ) {}

    public function approvalChanges(
        AuthenticatedPrincipal $actor,
        ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $row,
        string $resource,
        string $versionId,
    ): array {
        if (VersionLifecycleStatus::tryFrom((string) $row->status)?->isEditable() !== true) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.only_validated_draft_can_be_approved');
        }
        $validation = $this->validateCatalogDraftHandler->handle(new ValidateCatalogDraftCommand($actor, $resource, $versionId));
        if (! $validation->valid()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.catalog_validation_failed', details: CatalogValidationDocument::make($validation));
        }

        return [
            'status' => VersionLifecycleStatus::Approved->value,
            'approved_by' => $actor->userId,
            'approved_at' => $this->clock->now(),
        ];
    }

    public function publicationChanges(
        AuthenticatedPrincipal $actor,
        ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $row,
        string $resource,
        string $versionId,
    ): array {
        if ((string) $row->status !== VersionLifecycleStatus::Approved->value) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_approved_version_can_be_published');
        }
        $detail = $this->catalogReader->versionDetail($actor, $resource, $versionId);

        return [
            'status' => VersionLifecycleStatus::Published->value,
            'published_by' => $actor->userId,
            'published_at' => $this->clock->now(),
            'content_digest' => hash('sha256', json_encode(CatalogDocument::version($detail), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public function simpleTransition(
        ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord $row,
        string $from,
        string $to,
    ): array {
        if ((string) $row->status !== $from) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_can_transition', messageParams: ['from' => $from, 'to' => $to]);
        }

        return ['status' => $to];
    }
}
