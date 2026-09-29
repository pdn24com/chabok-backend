<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class CatalogRecordReader implements CatalogRecordReaderInterface
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ScheduleReaderInterface $scheduleReader,
        private CatalogReaderInterface $catalogReader,
        private CatalogRepositoryInterface $catalogRepository,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
    ) {}

    public function detail(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $id,
    ): CatalogRecordDetailDto {
        $this->catalogAccessGuard->authorizeRecord($actor);
        $kind = $this->catalogResourceDefinition->resource($resource);
        $identityId = $kind->identityKey();
        $versionId = $kind->versionKey();
        $identity = $this->catalogRepository->findTenantIdentity($kind, $id, $actor->hqId);
        if ($identity === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $revisionId = (string) $this->catalogRepository->latestVersionIdOf($kind, $id);
        $detail = $resource === 'commitment-schedules' ? $this->scheduleReader->versionDetail($actor, $revisionId) : $this->catalogReader->versionDetail($actor, $resource, $revisionId);
        if ($detail instanceof ServiceOfferingVersionRecord) {
            $detail->loadMissing(['serviceTypeVersion', 'shippingMethodVersion', 'commitmentBinding.scheduleVersion', 'optionRules.optionVersion']);
        }

        return new CatalogRecordDetailDto($identity, $detail);
    }
}
