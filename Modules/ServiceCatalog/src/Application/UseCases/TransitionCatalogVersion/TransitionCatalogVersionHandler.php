<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogVersionGuardInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class TransitionCatalogVersionHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private ConnectionInterface $connection,
        private CatalogVersionGuardInterface $catalogVersionGuard,
        private ClockInterface $clock,
        private CatalogChangeRecorderInterface $catalogChangeRecorder,
        private CatalogReaderInterface $catalogReader,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(TransitionCatalogVersionCommand $command): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $versionIdValue = $command->versionIdValue;
        $action = $command->action;
        $correlationId = $command->correlationId;
        $permission = match ($action) {
            'approve' => 'service_catalog.approve',
            'publish' => 'service_catalog.publish',
            default => 'service_catalog.manage_draft',
        };
        $this->catalogAccessGuard->assertAccess($actor, $permission);
        [, $versionId] = $this->catalogResourceDefinition->map($resource);

        return $this->connection->transaction(function () use ($actor, $resource, $versionIdValue, $action, $correlationId): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord {
            $kind = $this->catalogResourceDefinition->resource($resource);
            $row = $this->catalogRepository->lockTenantVersion($kind, $versionIdValue, $actor->hqId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $changes = match ($action) {
                'approve' => $this->catalogVersionGuard->approvalChanges($actor, $row, $resource, $versionIdValue),
                'publish' => $this->catalogVersionGuard->publicationChanges($actor, $row, $resource, $versionIdValue),
                'supersede' => $this->catalogVersionGuard->simpleTransition($row, VersionLifecycleStatus::Published->value, VersionLifecycleStatus::Superseded->value),
                'archive' => $this->catalogVersionGuard->simpleTransition($row, VersionLifecycleStatus::Superseded->value, VersionLifecycleStatus::Archived->value),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.unsupported_lifecycle_action'),
            };
            $row->forceFill($changes + ['updated_at' => $this->clock->now()])->save();
            $event = 'SERVICE_CATALOG_VERSION_'.mb_strtoupper($action).'D';
            $this->catalogChangeRecorder->record($actor, $event, 'SERVICE_CATALOG_VERSION', $versionIdValue, $correlationId, ['status' => $changes['status']]);

            return $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
        }, attempts: 3);
    }
}
