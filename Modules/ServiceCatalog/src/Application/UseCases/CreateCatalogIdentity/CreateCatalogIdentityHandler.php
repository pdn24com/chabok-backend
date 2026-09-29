<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogCodeInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogInputInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOptionVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class CreateCatalogIdentityHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CatalogCodeInterface $catalogCode,
        private CatalogInputInterface $catalogInput,
        private OfferingChildrenWriterInterface $offeringChildrenWriter,
        private CatalogChangeRecorderInterface $catalogChangeRecorder,
        private CatalogReaderInterface $catalogReader,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function handle(CreateCatalogIdentityCommand $command): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if ($resource === 'offerings' && in_array('availability_bindings', $input->presentFields, true)) {
            $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.availability.manage');
        }
        [$identityId] = $this->catalogResourceDefinition->map($resource);

        return $this->connection->transaction(function () use ($actor, $resource, $input, $correlationId, $identityId): ServiceTypeVersionRecord|ShippingMethodVersionRecord|ServiceOfferingVersionRecord|ServiceOptionVersionRecord {
            $now = $this->clock->now();
            $kind = $this->catalogResourceDefinition->resource($resource);
            $id = $this->catalogRepository->createIdentity($kind, [
                'hq_id' => $actor->hqId,
                'owner_key' => (string) $actor->hqId,
                'code' => ! empty($input->code) ? mb_strtoupper((string) $input->code) : $this->catalogCode->generate($resource, (string) $actor->hqId),
                'status' => 'ACTIVE',
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $version = $this->catalogInput->versionColumns($resource, $input) + [
                $identityId => $id,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'previous_version_id' => null,
                'status' => VersionLifecycleStatus::Draft->value,
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $draftId = $this->catalogRepository->createVersion($kind, $version);
            if ($resource === 'offerings') {
                $this->offeringChildrenWriter->replaceOfferingChildren($draftId, $input, $actor->hqId);
            }
            $this->catalogChangeRecorder->record($actor, 'SERVICE_CATALOG_IDENTITY_CREATED', mb_strtoupper(str_replace('-', '_', $resource)), $id, $correlationId, ['version_id' => $draftId]);

            return $this->catalogReader->versionDetail($actor, $resource, $draftId);
        }, attempts: 3);
    }
}
