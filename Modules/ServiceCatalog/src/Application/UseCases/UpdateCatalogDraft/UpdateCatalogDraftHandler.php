<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateCatalogDraftHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Application\Services\CatalogInput $catalogInput,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\OfferingChildrenWriter $offeringChildrenWriter,
        private \Modules\ServiceCatalog\Application\Services\CatalogChangeRecorder $catalogChangeRecorder,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
    )
    {
    }

    public function handle(UpdateCatalogDraftCommand $command): UpdateCatalogDraftResult
    {
        return new UpdateCatalogDraftResult($this->execute($command->actor, $command->resource, $command->versionIdValue, $command->expectedVersion, $command->input, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        int $expectedVersion,
        array $input,
        string $correlationId,
    ): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if ($resource === 'offerings' && array_key_exists('availability_bindings', $input)) {
            $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.availability.manage');
        }
        [, $versionId] = $this->catalogResourceDefinition->map($resource);
        return $this->transactions->run(function () use ($actor, $resource, $versionIdValue, $expectedVersion, $input, $correlationId, $versionId): array {
            $row = $this->catalog->lockVersion($actor->hqId, $resource, $versionIdValue);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((string) $row->status !== 'DRAFT') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only draft versions can be edited.');
            }
            if ((int) $row->lock_version !== $expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The draft changed since it was loaded.', details: ['current_version' => (int) $row->lock_version]);
            }
            $this->catalog->updateVersion($resource, $versionIdValue, $this->catalogInput->versionColumns($resource, $input) + ['lock_version' => $expectedVersion + 1, 'updated_at' => $this->clock->now()]);
            if ($resource === 'offerings') {
                $this->offeringChildrenWriter->replaceOfferingChildren($versionIdValue, $input, $actor->hqId);
            }
            $this->catalogChangeRecorder->record($actor, 'SERVICE_CATALOG_DRAFT_UPDATED', 'SERVICE_CATALOG_VERSION', $versionIdValue, $correlationId, ['lock_version' => $expectedVersion + 1]);
            return $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
        });
    }
}
