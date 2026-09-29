<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;

interface CatalogAuditRepositoryInterface
{
    /** Catalogue audit events of one tenant, newest first. @return LengthAwarePaginator<\Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord> */
    public function paginateCatalogEvents(?string $hqId, ?string $targetId, int $page, int $pageSize): LengthAwarePaginator;
}
