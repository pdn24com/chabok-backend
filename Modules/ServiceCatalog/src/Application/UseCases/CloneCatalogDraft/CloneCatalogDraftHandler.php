<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CloneCatalogDraftHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\OfferingChildrenWriter $offeringChildrenWriter,
        private \Modules\ServiceCatalog\Application\Services\CatalogChangeRecorder $catalogChangeRecorder,
    )
    {
    }

    public function handle(CloneCatalogDraftCommand $command): CloneCatalogDraftResult
    {
        return new CloneCatalogDraftResult($this->execute($command->actor, $command->resource, $command->identityIdValue, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $resource, string $identityIdValue, string $correlationId): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);
        return $this->transactions->run(function () use ($actor, $resource, $identityIdValue, $correlationId, $identityId, $versionId): array {
            $this->catalogReader->visibleIdentity($actor, $resource, $identityIdValue);
            $previous = (array) $this->catalog->lockLatestVersion($resource, $identityIdValue);
            if ($previous === []) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ($this->catalog->hasUnpublishedSuccessor($resource, $identityIdValue)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'An unpublished successor already exists.');
            }
            $newId = $this->identifiers->uuid();
            $previousId = (string) $previous[$versionId];
            unset($previous[$versionId], $previous['approved_by'], $previous['published_by'], $previous['approved_at'], $previous['published_at'], $previous['content_digest']);
            $previous[$versionId] = $newId;
            $previous['previous_version_id'] = $previousId;
            $previous['version_number'] = (int) $previous['version_number'] + 1;
            $previous['status'] = 'DRAFT';
            $previous['lock_version'] = 1;
            $previous['valid_from'] = null;
            $previous['valid_to'] = null;
            $previous['created_by'] = $actor->userId;
            $previous['created_at'] = $this->clock->now();
            $previous['updated_at'] = $this->clock->now();
            $this->catalog->insertVersion($resource, $previous);
            if ($resource === 'offerings') {
                $this->offeringChildrenWriter->cloneOfferingChildren((string) $previous['previous_version_id'], $newId);
            }
            $this->catalogChangeRecorder->record($actor, 'SERVICE_CATALOG_DRAFT_CLONED', 'SERVICE_CATALOG_VERSION', $newId, $correlationId, ['previous_version_id' => $previous['previous_version_id']]);
            return $this->catalogReader->versionDetail($actor, $resource, $newId);
        });
    }
}
