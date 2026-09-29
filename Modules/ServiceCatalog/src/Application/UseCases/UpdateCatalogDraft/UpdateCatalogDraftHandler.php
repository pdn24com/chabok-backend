<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogInputInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class UpdateCatalogDraftHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private ConnectionInterface $connection,
        private CatalogInputInterface $catalogInput,
        private ClockInterface $clock,
        private OfferingChildrenWriterInterface $offeringChildrenWriter,
        private CatalogChangeRecorderInterface $catalogChangeRecorder,
        private CatalogReaderInterface $catalogReader,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(UpdateCatalogDraftCommand $command): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $versionIdValue = $command->versionIdValue;
        $expectedVersion = $command->expectedVersion;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if ($resource === 'offerings' && in_array('availability_bindings', $input->presentFields, true)) {
            $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.availability.manage');
        }
        [, $versionId] = $this->catalogResourceDefinition->map($resource);

        return $this->connection->transaction(function () use ($actor, $resource, $versionIdValue, $expectedVersion, $input, $correlationId): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord {
            $kind = $this->catalogResourceDefinition->resource($resource);
            $row = $this->catalogRepository->lockTenantVersion($kind, $versionIdValue, $actor->hqId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ((string) $row->status !== VersionLifecycleStatus::Draft->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.only_draft_versions_can_be_edited');
            }
            if ((int) $row->lock_version !== $expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'servicecatalog.draft_changed_since_loaded', details: ['current_version' => (int) $row->lock_version]);
            }
            $this->catalogRepository->applyVersion($row, $this->catalogInput->versionColumns($resource, $input) + ['lock_version' => $expectedVersion + 1, 'updated_at' => $this->clock->now()]);
            if ($resource === 'offerings') {
                $this->offeringChildrenWriter->replaceOfferingChildren($versionIdValue, $input, $actor->hqId);
            }
            $this->catalogChangeRecorder->record($actor, 'SERVICE_CATALOG_DRAFT_UPDATED', 'SERVICE_CATALOG_VERSION', $versionIdValue, $correlationId, ['lock_version' => $expectedVersion + 1]);

            return $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
        }, attempts: 3);
    }
}
