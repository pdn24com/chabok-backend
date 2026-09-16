<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetCatalogHistoryHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
    )
    {
    }

    public function handle(GetCatalogHistoryCommand $command): GetCatalogHistoryResult
    {
        return new GetCatalogHistoryResult($this->execute($command->actor, $command->resource, $command->identityIdValue));
    }

    private function execute(AuthenticatedPrincipal $actor, string $resource, string $identityIdValue): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.history.view');
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);
        $this->catalogReader->visibleIdentity($actor, $resource, $identityIdValue);
        return array_map(fn($id) => $this->catalogReader->versionDetail($actor, $resource, (string) $id), $this->catalog->history($resource, $identityIdValue));
    }
}
