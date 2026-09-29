<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class CloneCatalogDraftHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private ConnectionInterface $connection,
        private CatalogReaderInterface $catalogReader,
        private ClockInterface $clock,
        private OfferingChildrenWriterInterface $offeringChildrenWriter,
        private CatalogChangeRecorderInterface $catalogChangeRecorder,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(CloneCatalogDraftCommand $command): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $identityIdValue = $command->identityIdValue;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);

        return $this->connection->transaction(function () use ($actor, $resource, $identityIdValue, $correlationId, $versionId): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord {
            $this->catalogReader->visibleIdentity($actor, $resource, $identityIdValue);
            $kind = $this->catalogResourceDefinition->resource($resource);
            // Serialize successors on the stable identity before inspecting or locking revisions.
            $this->catalogRepository->lockIdentity($kind, $identityIdValue);
            $previous = $this->catalogRepository->lockLatestVersionOf($kind, $identityIdValue);
            if ($previous === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($this->catalogRepository->hasVersionWithStatus($kind, $identityIdValue, VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::unpublished()))) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'pricing.unpublished_successor_already_exists');
            }
            $previousId = (string) $previous->getAttribute($versionId);
            $copy = $previous->replicate(['approved_by', 'published_by', 'approved_at', 'published_at', 'content_digest']);
            $copy->forceFill([
                'previous_version_id' => $previousId,
                'version_number' => (int) $previous->version_number + 1, 'status' => VersionLifecycleStatus::Draft->value, 'lock_version' => 1,
                'valid_from' => null, 'valid_to' => null, 'created_by' => $actor->userId,
                'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ])->save();
            $newId = (string) $copy->getKey();
            if ($resource === 'offerings') {
                $this->offeringChildrenWriter->cloneOfferingChildren($previousId, $newId);
            }
            $this->catalogChangeRecorder->record($actor, 'SERVICE_CATALOG_DRAFT_CLONED', 'SERVICE_CATALOG_VERSION', $newId, $correlationId, ['previous_version_id' => $previousId]);

            return $this->catalogReader->versionDetail($actor, $resource, $newId);
        }, attempts: 3);
    }
}
