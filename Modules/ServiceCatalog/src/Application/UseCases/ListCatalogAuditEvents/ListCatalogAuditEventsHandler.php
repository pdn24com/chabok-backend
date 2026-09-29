<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogAuditRepositoryInterface;

final readonly class ListCatalogAuditEventsHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogAuditRepositoryInterface $catalogAuditRepository,
    ) {}

    public function handle(ListCatalogAuditEventsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.audit.view');
        $page = $this->catalogAuditRepository->paginateCatalogEvents($actor->hqId, empty($filters['target_id']) ? null : (string) $filters['target_id'],
            max(1, (int) ($filters['page'] ?? 1)), min(100, max(1, (int) ($filters['page_size'] ?? 25))));
        // Audit API historically exposes the stored JSON strings; native reads retain that public contract.
        $page->through(function ($event): array {
            $document = $event->getRawOriginal();
            unset($document['id']);

            return $document;
        });

        return $page;
    }
}
