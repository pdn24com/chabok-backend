<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListCatalogAuditEventsHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
    )
    {
    }

    public function handle(ListCatalogAuditEventsCommand $command): ListCatalogAuditEventsResult
    {
        return new ListCatalogAuditEventsResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.audit.view');
        $page = $this->catalog->auditEvents($actor->hqId, $filters);
        return $page;
    }
}
