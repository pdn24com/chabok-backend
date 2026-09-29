<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;

final readonly class SetCatalogRecordActiveHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ConnectionInterface $connection,
        private CatalogRecordReaderInterface $catalogRecordReader,
        private ClockInterface $clock,
        private CurrentCatalogInterface $currentCatalog,
        private CatalogRecordChangeRecorderInterface $catalogRecordChangeRecorder,
        private CatalogRepositoryInterface $catalogRepository,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
    ) {}

    public function handle(SetCatalogRecordActiveCommand $command): CatalogRecordDetailDto
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $id = $command->id;
        $active = $command->active;
        $expected = $command->expected;
        $correlation = $command->correlation;
        $this->catalogAccessGuard->authorizeRecord($actor, true);

        return $this->connection->transaction(function () use ($actor, $resource, $id, $active, $expected, $correlation): CatalogRecordDetailDto {
            $kind = $this->catalogResourceDefinition->resource($resource);
            $identityId = $kind->identityKey();
            $identity = $this->catalogRepository->lockTenantIdentity($kind, $id, $actor->hqId);
            if ($identity === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $before = $this->catalogRecordReader->detail($actor, $resource, $id);
            $status = $active ? 'ACTIVE' : 'INACTIVE';
            if ($identity->status === $status) {
                return $before;
            }
            if ((int) $identity->edit_lock !== $expected) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'servicecatalog.record_changed_since_loaded');
            }
            $identity->forceFill(['status' => $status, 'edit_lock' => (int) $identity->edit_lock + 1, 'updated_at' => $this->clock->now()])->save();
            if ($active) {
                $this->currentCatalog->currentVersion($kind, $id, (string) $actor->hqId);
            }
            $after = $this->catalogRecordReader->detail($actor, $resource, $id);
            $this->catalogRecordChangeRecorder->record($actor, $id, $correlation, 'SERVICE_CATALOG_STATUS_CHANGED', $before, $after);

            return $after;
        }, attempts: 1);
    }
}
